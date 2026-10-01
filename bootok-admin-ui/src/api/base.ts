import axios from 'axios';
import type { AxiosRequestConfig } from 'axios';
import { getToken } from '@/utils/auth';
import field from '@/utils/field';

export interface ApiResponse<T = any> {
  code: string;
  message: string;
  data: T;
}

/**
 * 通用资源API
 * 模型名驼峰转kebab后拼接 /auto/{model} 参数化路由
 * 独立axios实例，请求/响应自动做驼峰⇄下划线转换
 */
export default class BaseApi {
  protected modelName: string = '';

  constructor(modelName: string = '') {
    this.modelName = field
      .strHump2Kebab(modelName)
      .replace(/^[\s-]+|[\s-]+$/g, '');
  }

  protected request<T>(
    method: string,
    url: string,
    params: Object = {}
  ): Promise<ApiResponse<T>> {
    // 拷贝一份，避免污染调用方数据；同时将驼峰字段转为下划线
    const data = Object.assign({}, params);
    field.hump2underline(data);
    return new Promise((resolve) => {
      axios
        .request(this.getRequestConfig(method, url, data))
        .then(function (res: any) {
          // 拦截器已解包一层，res 即 {code, data, message}；将下划线的字段修改为驼峰
          field.underline2hump(res.data);
          resolve(res);
        })
        .catch(function () {
          // 全局拦截器已统一提示
        });
    });
  }

  protected getRequestConfig(
    method: string,
    url: string,
    data: Object = {}
  ): AxiosRequestConfig {
    const requestConfig: AxiosRequestConfig = {
      baseURL: import.meta.env.VITE_API_BASE_URL as string,
      url,
      headers: {
        Authorization: `Bearer ${getToken() ?? ''}`,
      },
      method,
    } as AxiosRequestConfig;
    if (method === 'get') {
      requestConfig.params = data;
    } else {
      requestConfig.data = data;
    }
    return requestConfig;
  }

  protected get<T = any>(api: string, data: Object = {}): Promise<ApiResponse<T>> {
    return this.request('get', api, data);
  }

  protected post<T = any>(api: string, data: Object = {}): Promise<ApiResponse<T>> {
    return this.request('post', api, data);
  }

  protected put<T = any>(api: string, data: Object = {}): Promise<ApiResponse<T>> {
    return this.request('put', api, data);
  }

  protected delete<T = any>(api: string, data: Object = {}): Promise<ApiResponse<T>> {
    return this.request('delete', api, data);
  }

  public getForm() {
    return this.get(`/auto/${this.modelName}/form`);
  }

  public getAll<T = any>(data: Object = {}) {
    return this.get<T>(`/auto/${this.modelName}/all`, data);
  }

  public getRows<T = any>(page: number, data: Object = {}, pageSize: number = 15) {
    return this.post<T>(
      `/auto/${this.modelName}/rows?page=${page}&page_size=${pageSize}`,
      data
    );
  }

  public getItem<T = any>(id: number | string) {
    return this.get<T>(`/auto/${this.modelName}/${id}`);
  }

  public createItem<T = any>(data: Object) {
    return this.post<T>(`/auto/${this.modelName}`, data);
  }

  public updateItem<T = any>(id: number | string, data: Object = {}) {
    return this.put<T>(`/auto/${this.modelName}/${id}`, data);
  }

  public deleteItem<T = any>(id: number | string) {
    return this.delete<T>(`/auto/${this.modelName}/${id}`);
  }

  public deleteAttachment(id: string | number, fieldName: string) {
    const snakeField = field.strHump2Underline(fieldName);
    return this.delete(
      `/auto/${this.modelName}/attachment/${snakeField}/${id}`
    );
  }
}
