<?php
return [
    'profile' => 'spa',
    'stack' => 'vue',
    'entry' => 'src/main.js',
    'entries' => ['src/main.js'],
    'mount' => '#app',
    'manifest' => 'dist/.vite/manifest.json',
    'dev' => [
        // Prefer a real production manifest when one is present.
        // A forced dev session can still explicitly override this.
        'prefer_manifest' => true,
    ],
];
