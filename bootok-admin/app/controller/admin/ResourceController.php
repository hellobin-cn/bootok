<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\Model;
use app\service\ModelResolver;
use app\service\admin\ChangeLogService;
use app\service\DataScopeService;
use support\Response;

/**
 * 单一资源控制器
 * 承载 /auto/{model} 参数化路由的全部CRUD动作，模型从路由参数解析
 * 需要定制时：简单场景在模型上实现 form()/beforeSave()；复杂场景继承本类并设置 $modelClass 走独立路由
 */
class ResourceController extends Controller
{
    /** getAll 的硬上限，防止大表全量拉取 */
    private const ALL_LIMIT = 1000;

    /** 单次导出上限 */
    private const EXPORT_LIMIT = 10000;

    /**
     * 指定模型类（专用子类使用，跳过路由解析）
     */
    protected string $modelClass = '';

    /**
     * 解析模型并校验动作白名单，失败时直接返回错误响应
     */
    protected function resolve(string $action): Model|Response
    {
        if ($this->modelClass) {
            return new $this->modelClass();
        }
        $class = ModelResolver::resolve($this->request->route?->param('model') ?? '');
        if (!$class) {
            return $this->error(Code::NOT_FOUND->getDescription(), Code::NOT_FOUND->value);
        }
        if (!ModelResolver::attribute($class)->allows($action)) {
            return $this->error(Code::NOT_FOUND->getDescription(), Code::NOT_FOUND->value);
        }
        return new $class();
    }

    public function __construct(private readonly DataScopeService $dataScope)
    {
        parent::__construct();
    }

    public function create(): Response
    {
        $model = $this->resolve('create');
        if ($model instanceof Response) {
            return $model;
        }
        $model->setData($this->request->post());
        $this->assignDept($model);
        if ($message = $model->beforeSave()) {
            return $this->error($message);
        }
        if ($model->save()) {
            ChangeLogService::record($model, 'created');
            $model->afterSaved();
            return $this->success('添加成功');
        }
        return $this->error('添加失败');
    }

    public function getItem($id): Response
    {
        $model = $this->resolve('getItem');
        if ($model instanceof Response) {
            return $model;
        }
        $item = $model->where($model->getKeyName(), urldecode($id))->first();
        if ($item && !$this->dataScope->canRow($item, $this->adminId)) {
            $item = null;
        }
        if ($item) {
            $this->maskItems([$item], $model);
        }
        return $this->success($item);
    }

    public function edit($id): Response
    {
        $id = urldecode($id);
        $model = $this->resolve('edit');
        if ($model instanceof Response) {
            return $model;
        }
        /** @var Model|null $item */
        $item = $model->find($id);
        if (!$item) {
            return $this->error('数据不存在');
        }
        if (!$this->dataScope->canRow($item, $this->adminId)) {
            return $this->error('无权操作该数据');
        }
        $item->setData($this->request->post());
        if ($message = $item->beforeSave()) {
            return $this->error($message);
        }
        if ($item->save()) {
            ChangeLogService::record($item, 'updated');
            $item->afterSaved();
            return $this->success();
        }
        return $this->error('修改失败');
    }

    public function delete($id): Response
    {
        $id = urldecode($id);
        $model = $this->resolve('delete');
        if ($model instanceof Response) {
            return $model;
        }
        /** @var Model|null $item */
        $item = $model->find($id);
        if (!$item) {
            return $this->error('数据不存在');
        }
        if (!$this->dataScope->canRow($item, $this->adminId)) {
            return $this->error('无权操作该数据');
        }
        if ($item->delete()) {
            ChangeLogService::record($item, 'deleted', $item->getAttributes());
            $item->afterDeleted();
            return $this->success('删除成功');
        }
        return $this->error('删除失败');
    }

    /**
     * 回收站列表（仅启用软删除的模型）：onlyTrashed + 查询协议
     */
    public function trash(): Response
    {
        $model = $this->resolve('trash');
        if ($model instanceof Response) {
            return $model;
        }
        if (!method_exists($model, 'trashed')) {
            return $this->error('该资源未启用软删除', Code::PARAM_VALIDATE->value);
        }
        $pageSize = max(1, min(100, (int)$this->request->input('page_size', 15)));
        $paginator = $this->scoped($model)
            ->onlyTrashed()
            ->orderBy($model->getKeyName(), 'desc')
            ->paginate($pageSize, ['*'], 'page', (int)$this->request->input('page', 1));
        return $this->success($paginator);
    }

    /**
     * 从回收站恢复
     */
    public function restore($id): Response
    {
        $model = $this->resolve('restore');
        if ($model instanceof Response) {
            return $model;
        }
        if (!method_exists($model, 'trashed')) {
            return $this->error('该资源未启用软删除', Code::PARAM_VALIDATE->value);
        }
        $item = $model->newQuery()->onlyTrashed()->where($model->getKeyName(), urldecode($id))->first();
        if (!$item) {
            return $this->error('数据不存在');
        }
        if (!$item->restore()) {
            return $this->error('恢复失败');
        }
        return $this->success('恢复成功');
    }

    /**
     * 彻底删除（软删除模型专用，复用 delete 权限点）
     */
    public function forceDelete($id): Response
    {
        $model = $this->resolve('forceDelete');
        if ($model instanceof Response) {
            return $model;
        }
        if (!method_exists($model, 'trashed')) {
            return $this->error('该资源未启用软删除', Code::PARAM_VALIDATE->value);
        }
        $item = $model->newQuery()->onlyTrashed()->where($model->getKeyName(), urldecode($id))->first();
        if (!$item) {
            return $this->error('数据不存在');
        }
        if (!$this->dataScope->canRow($item, $this->adminId)) {
            return $this->error('无权操作该数据');
        }
        $original = $item->getAttributes();
        if (!$item->forceDelete()) {
            return $this->error('删除失败');
        }
        ChangeLogService::record($item, 'deleted', $original);
        $item->afterDeleted();
        return $this->success('已彻底删除');
    }

    public function deleteFile($field, $id): Response
    {
        $model = $this->resolve('deleteFile');
        if ($model instanceof Response) {
            return $model;
        }
        // 字段必须是真实表列，防止借路由覆写任意属性
        $field = urldecode($field);
        if (!in_array($field, $model->getColumns())) {
            return $this->error('字段不存在', Code::PARAM_VALIDATE->value);
        }
        /** @var Model|null $item */
        $item = $model->find(urldecode($id));
        if (!$item) {
            return $this->error('数据不存在');
        }
        $item->{$field} = '';
        if ($item->save()) {
            return $this->success('删除成功');
        }
        return $this->error('删除失败');
    }

    public function getAll(): Response
    {
        $model = $this->resolve('getAll');
        if ($model instanceof Response) {
            return $model;
        }
        $rows = $this->dataScope->apply($model->filter($this->request->all()), $model, $this->adminId)->limit(self::ALL_LIMIT)->get();
        $this->maskItems($rows, $model);
        return $this->success($rows);
    }

    public function getRows(): Response
    {
        $model = $this->resolve('getRows');
        if ($model instanceof Response) {
            return $model;
        }
        $pageSize = (int)($this->request->input('page_size', 15));
        $pageSize = max(1, min(100, $pageSize));
        $orderBy = (string)$this->request->input('order_by', $model->getKeyName());
        $orderType = strtolower((string)$this->request->input('order_type', 'desc')) == 'asc' ? 'asc' : 'desc';
        if (!in_array($orderBy, $model->getColumns())) {
            $orderBy = $model->getKeyName();
        }
        $paginator = $this->scoped($model)->orderBy($orderBy, $orderType)->paginate($pageSize, ['*'], 'page', (int)$this->request->input('page', 1));
        $this->maskItems($paginator->getCollection(), $model);
        return $this->success($paginator);
    }

    /**
     * Excel 导出：复用查询协议，默认上限 10000 行，脱敏字段同步打码
     */
    public function export(): Response
    {
        $model = $this->resolve('export');
        if ($model instanceof Response) {
            return $model;
        }
        $rows = $this->dataScope->apply($model->filter($this->request->post()), $model, $this->adminId)
            ->orderBy($model->getKeyName(), 'desc')
            ->limit(self::EXPORT_LIMIT)
            ->get();
        $this->maskItems($rows, $model);

        $map = $model->exportMap();
        $columns = $map ?: array_values(array_diff($model->getColumns(), $model->getHidden()));

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(array_values($map ?: array_combine($columns, $columns)), null, 'A1');
        $rowIndex = 2;
        foreach ($rows as $item) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $item->{$column};
            }
            $sheet->fromArray($values, null, "A{$rowIndex}");
            $rowIndex++;
        }

        $directory = runtime_path('export');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        // 清理一小时前的临时导出文件
        foreach (glob($directory . '/*.xlsx') ?: [] as $old) {
            if (filemtime($old) < time() - 3600) {
                @unlink($old);
            }
        }
        $path = $directory . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $name = $this->request->route?->param('model', 'export');
        return response('')->withHeaders([
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($name . '_' . date('YmdHis') . '.xlsx'),
        ])->withFile($path);
    }

    /**
     * 批量删除：复用 delete 权限点，单次上限 200 条
     */
    public function batchDelete(): Response
    {
        $model = $this->resolve('batchDelete');
        if ($model instanceof Response) {
            return $model;
        }
        $ids = $this->request->post('ids');
        if (!is_array($ids) || !$ids) {
            return $this->error('请选择要删除的数据', Code::PARAM_VALIDATE->value);
        }
        $ids = array_slice(array_map('intval', $ids), 0, 200);
        $success = 0;
        $fail = 0;
        foreach ($model->whereIn($model->getKeyName(), $ids)->get() as $item) {
            if (!$this->dataScope->canRow($item, $this->adminId)) {
                $fail++;
                continue;
            }
            if ($item->delete()) {
                ChangeLogService::record($item, 'deleted', $item->getAttributes());
                $item->afterDeleted();
                $success++;
            } else {
                $fail++;
            }
        }
        return $this->success(
            ['success' => $success, 'fail' => $fail],
            "已删除 {$success} 条" . ($fail > 0 ? "，失败 {$fail} 条" : '')
        );
    }


    /**
     * create 时自动归属：admin_id 强制挂当前操作人（数据权限的行来源闭环）。
     * admin_id 为模型主键时（sys_admin 自身）跳过，避免主键被覆盖成操作人 id。
     */
    private function assignDept(Model $model): void
    {
        $columns = $model->getColumns();
        if (in_array('admin_id', $columns, true) && $model->getKeyName() !== 'admin_id' && empty($model->admin_id) && $this->adminId > 0) {
            $model->admin_id = $this->adminId;
        }
        if (!in_array('dept_id', $columns, true) || !empty($model->dept_id)) {
            return;
        }
        $admin = \app\model\Admin::find($this->adminId);
        if ($admin !== null && $admin->dept_id > 0) {
            $model->dept_id = $admin->dept_id;
        }
    }

    /**
     * 读入口统一数据范围注入
     */
    private function scoped(Model $model): \Illuminate\Database\Eloquent\Builder
    {
        return $this->dataScope->apply($model->filter($this->request->post()), $model, $this->adminId);
    }

    /**
     * 就地脱敏：模型声明 $mask 的字段按规则打码（列表/详情/导出统一走此入口）
     */
    private function maskItems(iterable $items, Model $model): void
    {
        $fields = $model->maskFields();
        if (!$fields) {
            return;
        }
        foreach ($items as $item) {
            foreach ($fields as $field) {
                $value = $item->{$field} ?? null;
                if ($value !== null && $value !== '') {
                    $item->{$field} = Model::maskValue((string)$value);
                }
            }
        }
    }

    public function getForm(): Response
    {
        $model = $this->resolve('getForm');
        if ($model instanceof Response) {
            return $model;
        }
        return $this->success(array_map(fn($field) => $field->toArray(), $model->form()));
    }
}
