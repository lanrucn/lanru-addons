<?php

/**
 * LRCODE
 * ============================================================================
 * 版权所有 2016-2030 江苏蓝儒网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.lanru.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 潇声
 * Date: 2020-01
 */

namespace think\addons;

use think\facade\Config;
use think\exception\HttpException;
use think\facade\Hook;
use think\Loader;
use think\facade\Request;

class Route
{
    /**
     * 插件执行
     */
    public function execute($addon = null, $controller = null, $action = null)
    {
        $request = new Request();
        // 是否自动转换控制器和操作名
        $convert = Config::get('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';

        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) : 'index';
        $action = $action ? trim(call_user_func($filter, $action)) : 'index';

        Hook::listen('addon_begin', $request);
        if (!empty($addon) && !empty($controller) && !empty($action)) {
            $info = get_addon_info($addon);
            if (!$info) {
                throw new HttpException(404, '插件不存在...');
            }
            if (!$info['state']) {
                throw new HttpException(500, '插件未开启...');
            }

            // 监听addon_module_init
            Hook::listen('addon_module_init', $request);
            // 兼容旧版本行为,即将移除,不建议使用
            Hook::listen('addons_init', $request);

            $class = get_addon_class($addon, 'controller', $controller);

            if (!$class) {
                throw new HttpException(404, '插件'.Loader::parseName($controller, 1).'控制器未找到');
            }

            $instance = new $class($request);

            $vars = [];
            if (is_callable([$instance, $action])) {
                // 执行操作方法
                $call = [$instance, $action];
            } elseif (is_callable([$instance, '_empty'])) {
                // 空操作
                $call = [$instance, '_empty'];
                $vars = [$action];
            } else {
                // 操作不存在
                throw new HttpException(404, '插件'.get_class($instance) . '->' . $action . '()'.'控制器方法未找到');
            }

            Hook::listen('addon_action_begin', $call);

            return call_user_func_array($call, $vars);
        } else {
            abort(500, '插件不能为空');
        }
    }


}