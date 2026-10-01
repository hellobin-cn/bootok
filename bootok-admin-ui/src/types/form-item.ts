export interface RowData {
  label: string;
  value: number | string;
}

/**
 * 后端 getForm 下发的表单协议（app/ui 字段类 toArray 的输出）
 */
export default interface FormItem {
  field: string;
  type: string;
  label: string;
  data?: RowData[];
  storage?: string;
  help?: string;
  valueKey?: string;
  prepend?: string;
  append?: string;
  required?: boolean;
  default?: number | string;
  placeholder?: string;
  max?: number;
  min?: number;
  step?: number;
}
