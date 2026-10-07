<template>
  <div class="login-form-wrapper">
    <div class="login-form-title">Bootok Admin</div>
    <div class="login-form-sub-title">通用后台管理系统</div>
    <div class="login-form-error-msg">{{ errorMessage }}</div>
    <a-form
      ref="loginForm"
      :model="userInfo"
      class="login-form"
      layout="vertical"
      @submit="handleSubmit"
    >
      <a-form-item
        field="username"
        :rules="[{ required: true, message: $t('login.form.userName.errMsg') }]"
        :validate-trigger="['change', 'blur']"
        hide-label
      >
        <a-input
          v-model="userInfo.username"
          :placeholder="$t('login.form.userName.placeholder')"
        >
          <template #prefix>
            <icon-user />
          </template>
        </a-input>
      </a-form-item>
      <a-form-item
        field="password"
        :rules="[{ required: true, message: $t('login.form.password.errMsg') }]"
        :validate-trigger="['change', 'blur']"
        hide-label
      >
        <a-input-password
          v-model="userInfo.password"
          :placeholder="$t('login.form.password.placeholder')"
          allow-clear
        >
          <template #prefix>
            <icon-lock />
          </template>
        </a-input-password>
      </a-form-item>
      <a-form-item
        v-if="twoFaTicket"
        field="code"
        :rules="[{ required: true, message: '请输入 6 位动态码' }]"
        :validate-trigger="['change', 'blur']"
        hide-label
      >
        <a-input
          v-model="twoFaCode"
          placeholder="请输入验证器 6 位动态码"
          :max-length="6"
          allow-clear
        >
          <template #prefix>
            <icon-safe />
          </template>
        </a-input>
      </a-form-item>
      <a-form-item
        v-if="captchaEnabled"
        field="captcha_code"
        :rules="[{ required: true, message: '请输入图形验证码' }]"
        :validate-trigger="['change', 'blur']"
        hide-label
      >
        <a-input
          v-model="userInfo.captcha_code"
          placeholder="验证码"
          :max-length="4"
          allow-clear
          style="width: 200px"
        >
          <template #prefix>
            <icon-safe />
          </template>
        </a-input>
        <span
          class="captcha-box"
          title="点击刷新"
          @click="loadCaptcha"
          v-html="captchaSvg"
        />
      </a-form-item>
      <a-space :size="16" direction="vertical">
        <div v-if="!twoFaTicket" class="login-form-password-actions">
          <a-checkbox
            checked="rememberPassword"
            :model-value="loginConfig.rememberPassword"
            @change="setRememberPassword as any"
          >
            {{ $t('login.form.rememberPassword') }}
          </a-checkbox>
        </div>
        <a-link v-if="twoFaTicket" style="text-align: center" @click="resetTwoFa">
          返回重新登录
        </a-link>
        <a-button type="primary" html-type="submit" long :loading="loading">
          {{ twoFaTicket ? '验证并登录' : $t('login.form.login') }}
        </a-button>
      </a-space>
    </a-form>
  </div>
</template>

<script lang="ts" setup>
  import { ref, reactive, onMounted } from 'vue';
  import { useRouter } from 'vue-router';
  import { Message } from '@arco-design/web-vue';
  import { ValidatedError } from '@arco-design/web-vue/es/form/interface';
  import { useI18n } from 'vue-i18n';
  import { useStorage } from '@vueuse/core';
  import { useUserStore } from '@/store';
  import useLoading from '@/hooks/loading';
  import type { LoginData } from '@/api/user';
  import { login2fa, loginConfig as fetchLoginConfig, getCaptcha } from '@/api/user';
  import { setToken } from '@/utils/auth';

  const router = useRouter();
  const { t } = useI18n();
  const errorMessage = ref('');
  const { loading, setLoading } = useLoading();
  const userStore = useUserStore();

  const loginConfig = useStorage('login-config', {
    rememberPassword: true,
    username: '',
  });
  const userInfo = reactive({
    username: loginConfig.value.username,
    password: '',
    captcha_key: '',
    captcha_code: '',
  });

  // ===== 图形验证码（服务端开关开启后显示） =====
  const captchaEnabled = ref(false);
  const captchaSvg = ref('');

  async function loadCaptcha() {
    try {
      const res: any = await getCaptcha();
      captchaSvg.value = res.data?.svg ?? '';
      userInfo.captcha_key = res.data?.key ?? '';
      userInfo.captcha_code = '';
    } catch {
      // 忽略，登录时服务端会再拦
    }
  }

  onMounted(async () => {
    try {
      const res: any = await fetchLoginConfig();
      captchaEnabled.value = !!res.data?.captcha;
      if (captchaEnabled.value) {
        await loadCaptcha();
      }
    } catch {
      // 配置读取失败按未开启处理
    }
  });

  // 两步验证：登录响应 need_2fa 时进入动态码二步
  const twoFaTicket = ref('');
  const twoFaCode = ref('');

  const resetTwoFa = () => {
    twoFaTicket.value = '';
    twoFaCode.value = '';
    errorMessage.value = '';
  };

  const afterLogin = () => {
    const { redirect, ...othersQuery } = router.currentRoute.value.query;
    router.push({
      name: (redirect as string) || 'Workplace',
      query: {
        ...othersQuery,
      },
    });
    Message.success(t('login.form.login.success'));
  };

  const handleSubmit = async ({
    errors,
    values,
  }: {
    errors: Record<string, ValidatedError> | undefined;
    values: Record<string, any>;
  }) => {
    if (loading.value) return;
    if (!errors) {
      setLoading(true);
      try {
        if (twoFaTicket.value) {
          const res = await login2fa(twoFaTicket.value, twoFaCode.value);
          setToken(res.data.access_token);
          afterLogin();
          return;
        }
        const twoFa = await userStore.login(values as LoginData);
        if (twoFa && twoFa.need_2fa) {
          twoFaTicket.value = twoFa.ticket;
          errorMessage.value = '';
          return;
        }
        afterLogin();
        // 只记住用户名，密码不做本地存储
        const { rememberPassword } = loginConfig.value;
        const { username } = values;
        loginConfig.value.username = rememberPassword ? username : '';
      } catch (err) {
        errorMessage.value = (err as Error).message;
        // 验证码一次性：登录失败即换新图
        if (captchaEnabled.value) {
          loadCaptcha();
        }
      } finally {
        setLoading(false);
      }
    }
  };
  const setRememberPassword = (value: boolean) => {
    loginConfig.value.rememberPassword = value;
  };
</script>

<style lang="less" scoped>
  .login-form {
    &-wrapper {
      width: 320px;
    }

    &-title {
      color: var(--color-text-1);
      font-weight: 500;
      font-size: 24px;
      line-height: 32px;
    }

    &-sub-title {
      color: var(--color-text-3);
      font-size: 16px;
      line-height: 24px;
    }

    &-error-msg {
      height: 32px;
      color: rgb(var(--red-6));
      line-height: 32px;
    }

    &-password-actions {
      display: flex;
      justify-content: space-between;
    }

    &-register-btn {
      color: var(--color-text-3) !important;
    }
  }

  // 验证码图与输入框同高（Arco 默认输入框 36px）
  .captcha-box {
    display: inline-flex;
    align-items: center;
    height: 36px;
    margin-left: 8px;
    cursor: pointer;

    :deep(svg) {
      display: block;
      height: 36px;
      width: auto;
      border-radius: 4px;
    }
  }
</style>
