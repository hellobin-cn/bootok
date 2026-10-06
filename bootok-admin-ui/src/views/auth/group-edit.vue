<template>
  <a-card title="编辑权限组" :bordered="false">
    <a-form :model="form" label-align="right" auto-label-width style="max-width: 960px">
      <a-form-item label="名称" required>
        <a-input v-model="form.groupName" placeholder="填写权限组名称" />
      </a-form-item>
      <a-form-item label="数据范围" help="该组管理员可见的数据边界（业务数据按部门/创建人过滤）">
        <a-radio-group v-model="form.dataScope">
          <a-radio :value="1">全部数据</a-radio>
          <a-radio :value="2">本部门及以下</a-radio>
          <a-radio :value="3">仅本部门</a-radio>
          <a-radio :value="4">仅本人</a-radio>
        </a-radio-group>
      </a-form-item>
      <a-form-item label="权限">
        <a-table :data="sections" :pagination="false" style="width: 100%">
          <template #columns>
            <a-table-column title="板块" :width="140">
              <template #cell="{ record }">
                <a-checkbox
                  :model-value="record.checked"
                  :indeterminate="record.indeterminate"
                  @change="(v: any) => checkSection(record.name, v)"
                >
                  {{ record.title }}
                </a-checkbox>
              </template>
            </a-table-column>
            <a-table-column title="菜单" :width="320">
              <template #cell="{ record }">
                <a-space wrap>
                  <a-checkbox
                    v-for="menu in record.menus"
                    :key="menu.name"
                    :model-value="menu.checked"
                    @change="(v: any) => checkMenu(record.name, menu.name, v)"
                  >
                    {{ menu.title }}
                  </a-checkbox>
                </a-space>
              </template>
            </a-table-column>
            <a-table-column title="操作权限">
              <template #cell="{ record }">
                <a-space wrap>
                  <a-checkbox
                    v-for="perm in sectionPerms(record)"
                    :key="perm.mark"
                    :model-value="perm.checked"
                    @change="(v: any) => checkPerm(perm.mark, v)"
                  >
                    {{ perm.label }}
                  </a-checkbox>
                  <span v-if="!sectionPerms(record).length" class="empty-tip">无</span>
                </a-space>
              </template>
            </a-table-column>
          </template>
        </a-table>
      </a-form-item>
      <a-form-item>
        <a-space>
          <a-button type="primary" :loading="saving" @click="save">保存</a-button>
          <a-button @click="router.back()">返回</a-button>
        </a-space>
      </a-form-item>
    </a-form>
  </a-card>
</template>

<script lang="ts" setup>
  import { onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import { useRoute, useRouter } from 'vue-router';
  import BaseApi from '@/api/base';
  import { appRoutes } from '@/router/routes';

  interface MenuNode {
    name: string;
    title: string;
    checked: boolean;
    perms: { mark: string; label: string; checked: boolean }[];
  }

  interface Section {
    name: string;
    title: string;
    checked: boolean;
    indeterminate: boolean;
    menus: MenuNode[];
  }

  const route = useRoute();
  const router = useRouter();
  const groupId = parseInt((route.query.groupId as string) || '0', 10);

  const groupApi = new BaseApi('Group');
  const saving = ref(false);
  const sections = ref<Section[]>([]);
  const form = ref({
    groupName: '',
    purview: [] as string[],
    dataScope: 1 as number,
  });

  // 权限点显示名（去前缀）
  const permLabels: Record<string, string> = {
    rows: '查看',
    create: '新增',
    edit: '编辑',
    delete: '删除',
  };

  function permLabel(mark: string): string {
    const action = mark.split('.')[1] ?? '';
    return permLabels[action] ?? mark;
  }

  // 遍历前端路由表构建勾选结构（requiresAuth=false 的公共页跳过）
  function buildSections(purview: string[] = []) {
    const result: Section[] = [];
    appRoutes.forEach((item: any) => {
      if (!item.meta?.requiresAuth || !item.children?.length) return;
      const menus: MenuNode[] = [];
      item.children.forEach((child: any) => {
        if (!child.meta?.requiresAuth || !child.name) return;
        menus.push({
          name: child.name as string,
          title: (child.meta?.title ?? child.name) as string,
          checked: purview.includes(child.name as string),
          perms: (child.meta?.perms ?? []).map((mark: string) => ({
            mark,
            label: permLabel(mark),
            checked: purview.includes(mark),
          })),
        });
      });
      if (!menus.length) return;
      const checkedCount = menus.filter((m) => m.checked).length;
      result.push({
        name: item.name as string,
        title: (item.meta?.title ?? item.name) as string,
        checked: purview.includes(item.name as string),
        indeterminate: checkedCount > 0 && checkedCount < menus.length,
        menus,
      });
    });
    return result;
  }

  function sectionPerms(section: Section) {
    const marks = new Map<string, { mark: string; label: string; checked: boolean }>();
    section.menus.forEach((menu) => {
      menu.perms.forEach((p) => marks.set(p.mark, p));
    });
    return Array.from(marks.values());
  }

  function findSection(name: string) {
    return sections.value.find((s) => s.name === name);
  }

  function refreshSectionState(section: Section) {
    const checkedCount = section.menus.filter((m) => m.checked).length;
    section.checked = checkedCount > 0;
    section.indeterminate =
      checkedCount > 0 && checkedCount < section.menus.length;
  }

  function checkSection(name: string, status: boolean) {
    const section = findSection(name);
    if (!section) return;
    section.checked = status;
    section.indeterminate = false;
    section.menus.forEach((menu) => {
      menu.checked = status;
      menu.perms.forEach((p) => {
        p.checked = status;
      });
    });
  }

  function checkMenu(sectionName: string, menuName: string, status: boolean) {
    const section = findSection(sectionName);
    const menu = section?.menus.find((m) => m.name === menuName);
    if (!section || !menu) return;
    menu.checked = status;
    // 菜单取消时连带取消其权限点
    if (!status) {
      menu.perms.forEach((p) => {
        p.checked = false;
      });
    }
    refreshSectionState(section);
  }

  function checkPerm(mark: string, status: boolean) {
    sections.value.forEach((s) =>
      s.menus.forEach((m) =>
        m.perms.forEach((p) => {
          if (p.mark === mark) p.checked = status;
        })
      )
    );
  }

  function getPurview(): string[] {
    const purview: string[] = [];
    sections.value.forEach((section) => {
      if (section.checked) {
        purview.push(section.name);
      }
      section.menus.forEach((menu) => {
        if (menu.checked) {
          purview.push(menu.name);
        }
        menu.perms.forEach((p) => {
          if (p.checked) {
            purview.push(p.mark);
          }
        });
      });
    });
    return purview;
  }

  onMounted(async () => {
    if (!groupId) {
      sections.value = buildSections();
      return;
    }
    const res = await groupApi.getItem(groupId);
    form.value.groupName = res.data.groupName ?? '';
    form.value.purview = res.data.purview ?? [];
    form.value.dataScope = Number(res.data.dataScope ?? 1);
    sections.value = buildSections(form.value.purview);
  });

  async function save() {
    if (!form.value.groupName) {
      Message.error('名称不能为空');
      return;
    }
    form.value.purview = getPurview();
    if (!form.value.purview.length) {
      Message.error('权限不能为空');
      return;
    }
    saving.value = true;
    const res = groupId
      ? await groupApi.updateItem(groupId, form.value)
      : await groupApi.createItem(form.value);
    saving.value = false;
    if (res.code === '0') {
      Message.success('保存成功');
      router.back();
    } else {
      Message.error(res.message);
    }
  }
</script>

<style scoped>
  .empty-tip {
    color: var(--color-text-4);
  }
</style>
