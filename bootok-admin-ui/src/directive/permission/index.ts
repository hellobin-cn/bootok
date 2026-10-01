import { DirectiveBinding } from 'vue';
import usePermission from '@/hooks/permission';

/**
 * 按钮级权限指令
 * 用法：v-permission="'article.create'" 或 v-permission="['article.create','article.edit']"
 */
function checkPermission(el: HTMLElement, binding: DirectiveBinding) {
  const { value } = binding;
  if (!value) {
    return;
  }
  const permission = usePermission();
  if (!permission.hasPermission(value) && el.parentNode) {
    el.parentNode.removeChild(el);
  }
}

export default {
  mounted(el: HTMLElement, binding: DirectiveBinding) {
    checkPermission(el, binding);
  },
  updated(el: HTMLElement, binding: DirectiveBinding) {
    checkPermission(el, binding);
  },
};
