<?php

class PluginSoftwaremanagerUtils {
    public static function normalizeJsonString(string $raw): string {
        $s = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        $map = ['“'=>'"','”'=>'"','‘'=>'"','’'=>'"','，'=>',','：'=>':','；'=>';'];
        return trim(strtr($s, $map));
    }

    public static function normalizeEmail(string $raw): string {
        $s = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        $s = trim($s);
        if (function_exists('mb_convert_kana')) {
            $s = mb_convert_kana($s, 'asKV', 'UTF-8');
        }
        $map = ['＠' => '@', '．' => '.', '。' => '.', '，' => ',', '；' => ';', '“' => '"', '”' => '"', '‘' => "'", '’' => "'"];
        $s = strtr($s, $map);
        return trim($s, " <>\"'\t\r\n");
    }

    public static function shortenList(array $arr, int $max = 2): string {
        $arr = array_values(array_filter($arr, function($s){ return (string)$s !== ''; }));
        if (count($arr) <= $max) return implode('、', $arr);
        $first = array_slice($arr, 0, $max);
        return implode('、', $first) . '…(' . (count($arr)-$max) . ')';
    }
}


