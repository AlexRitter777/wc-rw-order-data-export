<?php

class Wc_Rw_Order_Data_Export_Transliterator {


    /**
     * Transliterates a string from Cyrillic to Latin characters.
     *
     * @param string $text The input string in Cyrillic.
     * @return string The transliterated string in Latin.
     */
    public static function transliterate(string $text) : string {

            $cyrillic_to_latin_map = [
                'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
                'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
                'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
                'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
                'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch',
                'Ш' => 'Sh', 'Щ' => 'Shch', 'Ъ' => "'", 'Ы' => 'Y', 'Ь' => "'",
                'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
                'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
                'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
                'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
                'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
                'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
                'ш' => 'sh', 'щ' => 'shch', 'ъ' => "'", 'ы' => 'y', 'ь' => "'",
                'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
            ];

            return strtr($text, $cyrillic_to_latin_map);

    }


}