<template>
  <div>
    <a-card :bordered="false" title="公众号接入" style="margin-bottom: 16px">
      <template #extra>
        <a-space>
          <a-badge
            :status="s.token_ok ? 'processing' : s.configured ? ('danger' as const) : ('normal' as const)"
            :text="s.token_ok ? '已连通' : s.configured ? '配置异常' : '未配置'"
          />
          <a-button size="small" :loading="loading" @click="load">刷新</a-button>
        </a-space>
      </template>
      <a-form :model="form" label-align="right" auto-label-width style="max-width: 720px">
        <a-form-item label="AppID">
          <a-input v-model="form.wechat_appid" placeholder="公众号 AppID" />
        </a-form-item>
        <a-form-item label="AppSecret">
          <a-input-password v-model="form.wechat_appsecret" placeholder="公众号 AppSecret" />
        </a-form-item>
        <a-form-item label="验证 Token" help="公众平台「服务器配置」中自定义的 Token，用于签名校验">
          <a-input v-model="form.wechat_token" placeholder="3-32 位字母数字" />
        </a-form-item>
        <a-form-item label="关注欢迎语" help="用户关注公众号时的自动回复">
          <a-input v-model="form.wechat_welcome" placeholder="欢迎关注！" />
        </a-form-item>
        <a-form-item label="服务器回调">
          <a-input :model-value="s.callback_url" readonly />
          <template #help>
            在公众平台「设置与开发-基本配置-服务器配置」填入本站域名 + 该路径，消息加解密选「明文模式」
          </template>
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" :loading="saving" @click="save">保存配置</a-button>
            <a-button :loading="testing" @click="test">连通测试</a-button>
          </a-space>
        </a-form-item>
      </a-form>
    </a-card>

    <a-card :bordered="false" title="自定义菜单">
      <template #extra>
        <a-space>
          <a-button size="small" :loading="menuLoading" @click="loadMenu">拉取线上菜单</a-button>
          <a-button size="small" status="danger" :loading="deleting" @click="removeMenu">删除菜单</a-button>
          <a-button type="primary" size="small" :loading="menuSaving" @click="saveMenu">发布菜单</a-button>
        </a-space>
      </template>
      <a-alert style="margin-bottom: 12px">
        一级菜单最多 3 个（每个可挂最多 5 个二级项）；类型「跳转链接」填 URL，「点击事件」填事件 Key。
      </a-alert>
      <div v-for="(btn, bi) in menu" :key="bi" class="menu-block">
        <div class="menu-row">
          <a-input v-model="btn.name" placeholder="一级菜单名" :max-length="16" style="width: 180px" />
          <a-select v-model="btn.type" style="width: 120px">
            <a-option value="view">跳转链接</a-option>
            <a-option value="click">点击事件</a-option>
          </a-select>
          <a-input
            v-model="btn[btn.type === 'view' ? 'url' : 'key']"
            :placeholder="btn.type === 'view' ? 'https://...' : '事件 Key'"
            style="flex: 1"
          />
          <a-button size="small" @click="addSub(btn)">+二级</a-button>
          <a-button size="small" status="danger" @click="menu.splice(bi, 1)">删除</a-button>
        </div>
        <div v-for="(sub, si) in btn.sub_button ?? []" :key="si" class="menu-row sub">
          <a-input v-model="sub.name" placeholder="二级菜单名" :max-length="40" style="width: 180px" />
          <a-select v-model="sub.type" style="width: 120px">
            <a-option value="view">跳转链接</a-option>
            <a-option value="click">点击事件</a-option>
          </a-select>
          <a-input
            v-model="sub[sub.type === 'view' ? 'url' : 'key']"
            :placeholder="sub.type === 'view' ? 'https://...' : '事件 Key'"
            style="flex: 1"
          />
          <a-button size="small" status="danger" @click="btn.sub_button?.splice(si, 1)">删除</a-button>
        </div>
      </div>
      <a-button v-if="menu.length < 3" long @click="addTop">+ 添加一级菜单</a-button>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';

  const s = ref<any>({});
  const form = ref<any>({ wechat_appid: '', wechat_appsecret: '', wechat_token: '', wechat_welcome: '' });
  const loading = ref(false);
  const saving = ref(false);
  const testing = ref(false);
  const menu = ref<any[]>([]);
  const menuLoading = ref(false);
  const menuSaving = ref(false);
  const deleting = ref(false);

  async function load() {
    loading.value = true;
    try {
      const res: any = await axios.get('/wechat/status');
      s.value = res.data ?? {};
      form.value.wechat_appid = s.value.appid ?? '';
      form.value.wechat_welcome = s.value.welcome ?? '';
    } finally {
      loading.value = false;
    }
  }

  async function save() {
    saving.value = true;
    try {
      // Secret 为空表示不修改（后端已存值，避免空值覆盖真实凭据）
      const items: Record<string, string> = {
        wechat_appid: form.value.wechat_appid,
        wechat_token: form.value.wechat_token,
        wechat_welcome: form.value.wechat_welcome,
      };
      if (form.value.wechat_appsecret) {
        items.wechat_appsecret = form.value.wechat_appsecret;
      }
      await axios.post('/config/settings', { items });
      Message.success('已保存');
      form.value.wechat_appsecret = '';
      await load();
    } finally {
      saving.value = false;
    }
  }

  async function test() {
    testing.value = true;
    try {
      const items: Record<string, string> = {
        wechat_appid: form.value.wechat_appid,
        wechat_token: form.value.wechat_token,
        wechat_welcome: form.value.wechat_welcome,
      };
      if (form.value.wechat_appsecret) {
        items.wechat_appsecret = form.value.wechat_appsecret;
      }
      await axios.post('/config/settings', { items });
      const res: any = await axios.get('/wechat/status');
      if (res.data?.token_ok) {
        Message.success('连通成功，凭据可用');
      } else {
        Message.error(res.data?.token_error || '连通失败');
      }
      s.value = res.data ?? {};
    } finally {
      testing.value = false;
    }
  }

  function addTop() {
    menu.value.push({ name: '', type: 'view', url: '', key: '', sub_button: [] });
  }

  function addSub(btn: any) {
    btn.sub_button = btn.sub_button ?? [];
    if (btn.sub_button.length >= 5) {
      Message.error('二级菜单最多 5 个');
      return;
    }
    btn.sub_button.push({ name: '', type: 'view', url: '', key: '' });
  }

  function toWechatButton(btn: any) {
    const out: any = { name: btn.name };
    if (btn.sub_button?.length) {
      out.sub_button = btn.sub_button.map((s2: any) => ({ name: s2.name, type: s2.type, [s2.type]: s2[s2.type] }));
    } else {
      out.type = btn.type;
      out[btn.type] = btn[btn.type];
    }
    return out;
  }

  async function loadMenu() {
    menuLoading.value = true;
    try {
      const res: any = await axios.get('/wechat/menu');
      const buttons = res.data?.selfmenu_info?.button ?? [];
      menu.value = buttons.map((b: any) => ({
        name: b.name,
        type: b.type ?? (b.sub_button?.list?.[0]?.type ?? 'view'),
        url: b.url ?? b.sub_button?.list?.[0]?.url ?? '',
        key: b.key ?? b.sub_button?.list?.[0]?.key ?? '',
        sub_button: (b.sub_button?.list ?? []).map((s2: any) => ({
          name: s2.name, type: s2.type ?? 'view', url: s2.url ?? '', key: s2.key ?? '',
        })),
      }));
      Message.success('已拉取线上菜单');
    } finally {
      menuLoading.value = false;
    }
  }

  async function saveMenu() {
    const invalid = menu.value.find((b) => !b.name || ((b.type === 'view' && !b.url) || (b.type === 'click' && !b.key)));
    if (invalid) {
      Message.error('请补全菜单名称与对应的 URL / 事件 Key');
      return;
    }
    menuSaving.value = true;
    try {
      const res: any = await axios.post('/wechat/menu', { button: menu.value.map(toWechatButton) });
      Message.success(res.message || '菜单已发布');
    } finally {
      menuSaving.value = false;
    }
  }

  async function removeMenu() {
    deleting.value = true;
    try {
      const res: any = await axios.delete('/wechat/menu');
      Message.success(res.message || '已删除');
      menu.value = [];
    } finally {
      deleting.value = false;
    }
  }

  onMounted(load);
</script>

<style scoped lang="less">
  .menu-block {
    margin-bottom: 12px;
    padding: 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
  }
  .menu-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    align-items: center;
    &.sub {
      margin-left: 24px;
    }
  }
</style>
