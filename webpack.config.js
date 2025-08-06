// Webpack config for Nextcloud Door Estimator app frontend
// Uses @nextcloud/webpack-config as base, extended for React+TypeScript

const NextcloudWebpackConfig = require('@nextcloud/webpack-config');
const path = require('path');

/** @type {import('webpack').Configuration} */
module.exports = (env, argv) => {
    const isDev = argv.mode === 'development';

    // Extend Nextcloud base config
    const baseConfig = NextcloudWebpackConfig({
        // No default entry, we override below
    });

    // Custom entry points (both TSX files, bundled together)
    baseConfig.entry = [
        path.resolve(__dirname, 'door_estimator_webapp.tsx'),
        path.resolve(__dirname, 'QuoteSection.tsx')
    ];

    // Output: single bundle
    baseConfig.output = {
        path: path.resolve(__dirname, 'dist'),
        filename: 'door-estimator.js',
        publicPath: '/apps/nextcloud-door-estimator/js/', // Nextcloud app static path
        clean: true
    };

    // Module rules: TypeScript + React JSX
    baseConfig.module.rules.push(
        {
            test: /\.(ts|tsx)$/,
            use: [
                {
                    loader: 'ts-loader',
                    options: {
                        transpileOnly: true,
                        compilerOptions: {
                            jsx: 'react-jsx'
                        }
                    }
                }
            ],
            exclude: /node_modules/
        },
        {
            test: /\.js$/,
            enforce: 'pre',
            use: ['source-map-loader']
        }
    );

    // Resolve extensions for TS, TSX, JS, JSX
    baseConfig.resolve = {
        ...baseConfig.resolve,
        extensions: ['.ts', '.tsx', '.js', '.jsx', ...(baseConfig.resolve?.extensions || [])],
        alias: {
            ...(baseConfig.resolve?.alias || {}),
            // Add any Nextcloud-specific aliases here if needed
        }
    };

    // Enforce devtool=false for CSP compliance: prevents use of eval/Function in build output.
    // This disables all source maps and overrides any value from @nextcloud/webpack-config or environment variables.
    baseConfig.devtool = false;

    // Dev server config (for local development)
    baseConfig.devServer = {
        static: {
            directory: path.join(__dirname, 'js'),
            publicPath: '/js/'
        },
        compress: true,
        port: 9000,
        hot: true,
        devMiddleware: {
            writeToDisk: true
        },
        allowedHosts: 'all',
        headers: {
            'Access-Control-Allow-Origin': '*'
        },
        // Proxy to Nextcloud backend if needed (uncomment and adjust as needed)
        // proxy: {
        //     '/apps/nextcloud-door-estimator/api': 'http://localhost:8080'
        // }
    };

    return baseConfig;
};