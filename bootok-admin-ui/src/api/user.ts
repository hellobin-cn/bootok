import axios from 'axios';
import type { HttpResponse } from '@/api/interceptor';

export interface LoginData {
  username: string;
  password: string;
}

export interface LoginRes {
  access_token: string;
  access_token_exp: number;
}

export interface UserInfoRes {
  admin_name: string;
  is_super: number;
  purview: string[];
}

export function login(data: LoginData) {
  return axios.post('/login', data) as unknown as Promise<HttpResponse<LoginRes>>;
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
