import { createAppConfig } from "@nextcloud/vite-config";
import { join, resolve } from "path";
import vue from '@vitejs/plugin-vue';
import tsconfigPaths from 'vite-tsconfig-paths';
import { defineConfig } from 'vite';

export default createAppConfig(
  {
    main: resolve(join("src", "main.ts")),
  },
  {
    createEmptyCSSEntryPoints: true,
    extractLicenseInformation: true,
    thirdPartyLicense: false,
    plugins: [
      vue({
        template: {
          compilerOptions: {
            // Treat Nextcloud Vue components as custom elements
            isCustomElement: (tag) => tag.startsWith('nextcloud-')
          }
        }
      }),
      tsconfigPaths()
    ],
    resolve: {
      alias: {
        '@': resolve('src'),
        'utils': resolve('utils'),
        'types': resolve('types'),
        'services': resolve('src/services'),
        'components': resolve('src/components'),
        'views': resolve('src/views'),
        'stores': resolve('src/stores')
      },
    },
    define: {
      // Define global constants for the app
      __APP_VERSION__: JSON.stringify(process.env.npm_package_version || '1.0.0'),
      __BUILD_TIME__: JSON.stringify(new Date().toISOString())
    },
    build: {
      // Optimize build for production
      target: 'es2020',
      minify: 'terser',
      terserOptions: {
        compress: {
          drop_console: true,
          drop_debugger: true
        }
      },
      rollupOptions: {
        output: {
          // Optimize chunk splitting for better caching
          manualChunks: (id) => {
            // Vendor chunks
            if (id.includes('node_modules')) {
              if (id.includes('@nextcloud/vue')) {
                return 'nextcloud-vue';
              }
              if (id.includes('vue') || id.includes('pinia')) {
                return 'vue-vendor';
              }
              if (id.includes('zod') || id.includes('validation')) {
                return 'validation';
              }
              return 'vendor';
            }
            
            // Admin components in separate chunk for lazy loading
            if (id.includes('AdminView') || id.includes('admin')) {
              return 'admin';
            }
            
            // Services and utilities
            if (id.includes('services') || id.includes('utils')) {
              return 'services';
            }
            
            // Main app chunk
            return 'main';
          }
        }
      },
      // Optimize chunk size warnings
      chunkSizeWarningLimit: 1000
    },
    server: {
      // Development server configuration
      port: 3000,
      host: 'localhost',
      hmr: {
        port: 3001
      }
    }
  }
);