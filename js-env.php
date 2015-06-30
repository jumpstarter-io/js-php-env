<?php

define("JS_ENV_PATH", "/app/env.json");
define("JS_ENV_APC_KEY", "jumpstarter-env");

function js_get_env($env_path = JS_ENV_PATH, $apc_key = JS_ENV_APC_KEY) {
    // Fetch parsed environment from request cache.
    static $envs = array();
    if (isset($envs[$env_path]) && $envs[$env_path] !== null)
        return $envs[$env_path];
    // Fetch parsed environment from server cache.
    if (function_exists("apc_fetch")) {
        $env = apc_fetch($apc_key);
        if (is_array($env)) {
            $envs[$env_path] = $env;
            return $env;
        }
    }
    // Read environment and parse it.
    $env = json_decode(file_get_contents($env_path), true);
    if (!is_array($env))
        throw new Exception("could not parse $env_path (not jumpstarter container?)");
    // Store parsed environment in server cache.
    if (function_exists("apc_store")) {
        apc_store($apc_key, $env);
    }
    $envs[$env_path] = $env;
    return $env;
}

function js_env_get_value($key_path, $env_path = JS_ENV_PATH, $apc_key = JS_ENV_APC_KEY) {
    static $cache = array();
    if (isset($cache[$env_path][$key_path]))
        return $cache[$env_path][$key_path];
    $env = js_get_env($env_path, $apc_key);
    $path_arr = explode(".", $key_path);
    if (count($path_arr) === 0)
        return null;
    $obj = $env;
    foreach($path_arr as $part) {
        if (!isset($obj[$part])) {
            $cache[$env_path][$key_path] = null;
            return null;
        }
        $obj = $obj[$part];
    }
    $cache[$env_path][$key_path] = $obj;
    return $obj;
}

function js_env_get_value_or_array($path, $env_path = JS_ENV_PATH, $apc_key = JS_ENV_APC_KEY) {
    $obj = js_env_get_value($path, $env_path, $apc_key);
    return is_array($obj)? $obj: array();
}

function js_env_get_siteurl() {
    // Primarily use top user domain if one is configured.
    $user_domains = js_env_get_value_or_array("settings.core.user-domains");
    if (is_array($user_domains) && count($user_domains) > 0) {
        $preferred = reset($user_domains);
        foreach ($user_domains as $domain) {
            if ($domain["preferred"]) {
                $preferred = $domain;
                break;
            }
        }
        if (empty($preferred["name"]))
            throw new Exception("corrupt env: preferred domain has no name");
        return ($domain["secure"]? "https": "http") . "://" . $preferred["name"];
    }
    // Fall back to auto domain (always encrypted).
    $auto_domain = js_env_get_value("settings.core.auto-domain");
    if (empty($auto_domain))
        throw new Exception("auto-domain not found in env");
    return "https://" . $auto_domain;
}
