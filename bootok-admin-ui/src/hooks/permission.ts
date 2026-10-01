import { RouteLocationNormalized, RouteRecordRaw } from 'vue-router';
import { useUserStore } from '@/store';

/**
 * 权限判断（purview 模式）
 * 权限集合 = 前端路由name + 按钮权限点（如 article.create），由后端 /user/info 下发
 */
export default function usePermission() {
  const userStore = useUserStore();

  const hasPurview = (name: string) =>
    userStore.isSuper || userStore.purview.includes(name);

  return {
    /**
     * 路由是否可访问：无鉴权标记、超管、或路由name在权限集合内
     */
    accessRouter(route: RouteLocationNormalized | RouteRecordRaw) {
      if (!route.meta?.requiresAuth) return true;
      return hasPurview(route.name as string);
    },

    /**
     * 按钮权限点是否可用，v-permission 指令与手动调用均走这里
     */
    hasPermission(value: string | string[]) {
      if (userStore.isSuper) return true;
      if (typeof value === 'string') {
        return userStore.purview.includes(value);
      }
      return value.length > 0 && userStore.purview.some((item) => value.includes(item));
    },

    /**
     * 找到第一个可访问的路由作为兜底跳转
     */
    findFirstPermissionRoute(_routers: any, _role = 'admin') {
      const cloneRouters = [..._routers];
      while (cloneRouters.length) {
        const firstElement = cloneRouters.shift();
        if (!firstElement) continue;
        if (firstElement?.children?.length) {
          cloneRouters.unshift(...firstElement.children);
          continue;
        }
        if (this.accessRouter(firstElement)) {
          return { name: firstElement.name };
        }
      }
      return null;
    },
  };
}
