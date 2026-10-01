<?php

namespace app\controller;

use app\enum\Code;
use app\model\Model;
use app\service\ModelResolver;
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

    /**
     * 指定模型类（专用子类使用，跳过路由解析）
     */
    protected string $modelClass = '';

    /**
     * 解析模型并校验动作白名单，失败时直接返回错误响应
     *
     * 返回类型放宽为 support\Model：自动资源模型必须继承 app\model\Model
     * （filter/setData/form 等自动资源能力都在基类），但基类不匹配时
     * 给出明确报错，而不是深层调用的 TypeError
     */
    protected function resolve(string $action): \support\Model|Response
    {
        if ($this->modelClass) {
            $model = new $this->modelClass();
        } else {
            $class = ModelResolver::resolve($this->request->route?->param('model') ?? '');
            if (!$class) {
                return $this->error(Code::NOT_FOUND->getDescription(), Code::NOT_FOUND->value);
            }
            if (!ModelResolver::attribute($class)->allows($action)) {
                return $this->error(Code::NOT_FOUND->getDescription(), Code::NOT_FOUND->value);
            }
            $model = new $class();
        }
        if (!$model instanceof Model) {
            throw new \RuntimeException(sprintf(
                '自动资源模型 %s 必须继承 %s（filter/setData/form 等能力依赖基类）',
                get_class($model),
                Model::class
            ));
        }
        return $model;
    }

    public function create(): Response
    {
        $model = $this->resolve('create');
        if ($model instanceof Response) {
            return $model;
        }
        $model->setData($this->request->post());
        if ($message = $model->beforeSave()) {
            return $this->error($message);
        }
        if ($model->save()) {
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
        return $this->success($model->where($model->getKeyName(), urldecode($id))->first());
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
        $item->setData($this->request->post());
        if ($message = $item->beforeSave()) {
            return $this->error($message);
        }
        if ($item->save()) {
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
        if ($item->delete()) {
            $item->afterDeleted();
            return $this->success('删除成功');
        }
        return $this->error('删除失败');
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
        return $this->success($model->filter($this->request->all())->limit(self::ALL_LIMIT)->get());
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
        return $this->success(
            $model->filter($this->request->post())->orderBy($orderBy, $orderType)->paginate($pageSize, ['*'], 'page', (int)$this->request->input('page', 1))
        );
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
