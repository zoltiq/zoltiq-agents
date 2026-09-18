<?php

namespace Zoltiq\Agents\Services;

class Resource_Discovery {

    private const CACHE_KEY_WIDGETS = 'zoltiq_agents_widgets_cache';

    
    public function get_widgets(): array {
        
        $tools_dir = ZOLTIQ_AGENTS_PATH . 'includes/tools/';
        $json_dir = $tools_dir . 'widgets/';

        // Check the folder modification time
        $folder_mtime = filemtime($json_dir);
        //$cached = get_transient(self::CACHE_KEY);

        // If cache exists AND folder modification time matches the cached one — return cached data
        if (isset($cached['time']) && $cached['time'] === $folder_mtime) {
            return $cached['widgets'];
        }
        
        $discovered = [];
        $files = glob($json_dir . '*.json');
       
        foreach ($files as $json_file) {
            $json_data = json_decode(file_get_contents($json_file), true);
            $surface_id = $json_data['createSurface']['surfaceId'];
            $discovered[$surface_id] = $json_data;
        }
   
        // Store the new list along with the current folder modification time
        set_transient(self::CACHE_KEY_WIDGETS, [
            'time'  => $folder_mtime,
            'widgets' => $discovered
        ], DAY_IN_SECONDS);

        return $discovered;
    }
}