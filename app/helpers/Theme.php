<?php
declare(strict_types=1);
class Theme {
    private static $current = null;
    private static $all = [];
    public static function load() {
        try {
            $s = Database::fetch("SELECT value FROM settings WHERE `key`='active_theme'");
            $slug = $s ? $s['value'] : 'ak-dark';
            self::$current = Database::fetch("SELECT * FROM themes WHERE slug=?", [$slug]);
            if (!self::$current) self::$current = Database::fetch("SELECT * FROM themes WHERE is_active=1 LIMIT 1");
            if (!self::$current) self::$current = Database::fetch("SELECT * FROM themes ORDER BY id LIMIT 1");
        } catch (Exception $e) { self::$current = null; }
        if (!self::$current) self::$current = self::fallback();
    }
    public static function current() {
        if (self::$current === null) self::load();
        return self::$current;
    }
    public static function all() {
        if (empty(self::$all)) {
            try { self::$all = Database::fetchAll("SELECT * FROM themes ORDER BY id"); }
            catch (Exception $e) { self::$all = []; }
        }
        return self::$all;
    }
    public static function cssVariables() {
        $t = self::current();
        return ":root{--color-primary:{$t['primary_color']};--color-secondary:{$t['secondary_color']};--color-accent:{$t['accent_color']};--color-bg:{$t['background']};--color-surface:{$t['surface']};--color-text:{$t['text_primary']};--color-text-muted:{$t['text_secondary']};--color-border:{$t['border_color']};}";
    }
    public static function activate($slug) {
        $theme = Database::fetch("SELECT id FROM themes WHERE slug=?", [$slug]);
        if (!$theme) return false;
        Database::query("UPDATE themes SET is_active=0");
        Database::update('themes', ['is_active'=>1], 'slug=?', [$slug]);
        $ex = Database::fetch("SELECT id FROM settings WHERE `key`='active_theme'");
        if ($ex) Database::update('settings', ['value'=>$slug], '`key`=?', ['active_theme']);
        else Database::insert('settings', ['key'=>'active_theme','value'=>$slug,'type'=>'string']);
        self::$current = null; self::$all = [];
        return true;
    }
    private static function fallback() {
        return ['name'=>'AK Dark','slug'=>'ak-dark','is_dark'=>1,'primary_color'=>'#e11d48','secondary_color'=>'#f43f5e','accent_color'=>'#fb7185','background'=>'#0a0a0a','surface'=>'#171717','text_primary'=>'#fafafa','text_secondary'=>'#a3a3a3','border_color'=>'#262626'];
    }
}
