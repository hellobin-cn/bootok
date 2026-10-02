import { mergeConfig } from 'vite';
import baseConfig from './vite.config.base';

export default mergeConfig(
  {
    mode: 'development',
    server: {
      open: true,
      fs: {
        strict: true,
      },
      proxy: {
        // 后端 webman 服务（端口由环境变量覆盖；规范端口 8787，本机避让 zcy/bxy 时用 8789）
        '/api': {
          target: process.env.VITE_PROXY_TARGET || 'http://127.0.0.1:8789',
          changeOrigin: true,
          rewrite: (path: string) => path.replace(/^\/api/, ''),
        },
      },
    },
  },
  baseConfig
);
