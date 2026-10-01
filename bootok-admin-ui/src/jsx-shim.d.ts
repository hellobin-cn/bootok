// vue-tsc 对 .vue 内 tsx 渲染函数的 JSX 内建元素类型补充
declare global {
  namespace JSX {
    interface IntrinsicElements {
      [name: string]: any;
    }
  }
}

export {};
