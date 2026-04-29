<?php

class PluginPgeservicosPortal extends CommonGLPI {

    static $rightname = 'ticket';

    static function getTypeName($nb = 0) {
        return 'PGE Serviços';
    }

    static function getMenuName() {
        return 'PGE Serviços';
    }

    static function getMenuContent() {
        $menu = [];

        if (Session::haveRight('ticket', READ)) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = '/plugins/pgeservicos/front/index.php';
            $menu['icon']  = 'ti ti-layout-grid';
        }

        return $menu;
    }
}
