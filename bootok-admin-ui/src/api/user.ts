import axios from 'axios';
import type { HttpResponse } from '@/api/interceptor';

export interface LoginData {
  username: string;
  password: string;
}

export interface LoginRes {
  access_token: string;
  access_token_exp: number;
  // 两步验证分支：need_2fa=true 时无 token，凭 ticket 二次提交
  need_2fa?: boolean;
  ticket?: string;
  expires_in?: number;
}

export interface UserInfoRes {
  admin_name: string;
  is_super: number;
  purview: string[];
  totp_enabled?: number;
}

export function login(data: LoginData) {
  return axios.post('/login', data) as unknown as Promise<HttpResponse<LoginRes>>;
}

export function login2fa(ticket: string, code: string) {
  return axios.post('/login/2fa', { ticket, code }) as unknown as Promise<HttpResponse<LoginRes>>;
}

export interface TotpSetupRes {
  secret: string;
  uri: string;
}

export function totpSetup() {
  return axios.post('/admin/totp/setup') as unknown as Promise<HttpResponse<TotpSetupRes>>;
}

export function totpEnable(code: string) {
  return axios.post('/admin/totp/enable', { code }) as unknown as Promise<HttpResponse<null>>;
}

export function totpDisable(password: string, code: string) {
  return axios.post('/admin/totp/disable', { password, code }) as unknown as Promise<HttpResponse<null>>;
}

export function logout() {
  return axios.post('/logout') as unknown as Promise<HttpResponse<null>>;
}

export function getUserInfo() {
  return axios.get('/user/info') as unknown as Promise<
    HttpResponse<UserInfoRes>
  >;
}

export function updatePassword(data: {
  old_password: string;
  new_password: string;
  re_password: string;
}) {
  return axios.post('/password', data) as unknown as Promise<HttpResponse<null>>;
}
