import axios from 'axios';
import type { AxiosRequestConfig, AxiosResponse } from 'axios';
import { Message } from '@arco-design/web-vue';
import router from '@/router';
import { clearToken, getToken, setToken } from '@/utils/auth';

/**
 * 后端统一响应结构
 */
export interface HttpResponse<T = unknown> {
  code: string;
  message: string;
  data: T;
  request_id: string;
}

if (import.meta.env.VITE_API_BASE_URL) {
  axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL;
}

axios.interceptors.request.use(
  (config: AxiosRequestConfig) => {
    const token = getToken();
    if (token) {
      if (!config.headers) {
        config.headers = {};
      }
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

axios.interceptors.response.use(
  (response: AxiosResponse<HttpResponse>) => {
    // 滑动续期：后端在token临近过期时通过响应头下发新token
    const renewed = (response.headers as any)?.['x-renewed-token'];
    if (renewed) {
      setToken(String(renewed));
    }
    // 二进制流（文件下载 / Excel 导出）不走业务信封检查，原样放行完整 response，
    // 调用方从 res.data（Blob）/ res.headers 取产物；出错时后端返回 JSON 信封，自然落入下方统一提示
    if (response.data instanceof Blob) {
      return response;
    }
    const res = response.data;
    if (res.code === '0') {
      return res as any;
    }
    // 登录失效，清理凭证回到登录页
    if (res.code === '4001') {
      clearToken();
      Message.error(res.message || '登录已失效');
      if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' });
      }
      return Promise.reject(new Error(res.message || '登录已失效'));
    }
    Message.error({
      content: res.message || '请求失败',
      duration: 3 * 1000,
    });
    return Promise.reject(new Error(res.message || '请求失败'));
  },
  (error) => {
    Message.error({
      content: error?.message || '网络异常',
      duration: 3 * 1000,
    });
    return Promise.reject(error);
  }
);
