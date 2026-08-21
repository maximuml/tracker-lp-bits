<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('countries')->delete();

        DB::table('countries')->insert([
            0 => [
                'id' => 1,
                'name' => 'Sweden',
                'flagpic' => 'sweden.gif',
            ],
            1 => [
                'id' => 2,
                'name' => 'United States of America',
                'flagpic' => 'usa.gif',
            ],
            2 => [
                'id' => 3,
                'name' => 'Russia',
                'flagpic' => 'russia.gif',
            ],
            3 => [
                'id' => 4,
                'name' => 'Finland',
                'flagpic' => 'finland.gif',
            ],
            4 => [
                'id' => 5,
                'name' => 'Canada',
                'flagpic' => 'canada.gif',
            ],
            5 => [
                'id' => 6,
                'name' => 'France',
                'flagpic' => 'france.gif',
            ],
            6 => [
                'id' => 7,
                'name' => 'Germany',
                'flagpic' => 'germany.gif',
            ],
            7 => [
                'id' => 8,
                'name' => '中国',
                'flagpic' => 'china.gif',
            ],
            8 => [
                'id' => 9,
                'name' => 'Italy',
                'flagpic' => 'italy.gif',
            ],
            9 => [
                'id' => 10,
                'name' => 'Denmark',
                'flagpic' => 'denmark.gif',
            ],
            10 => [
                'id' => 11,
                'name' => 'Norway',
                'flagpic' => 'norway.gif',
            ],
            11 => [
                'id' => 12,
                'name' => 'United Kingdom',
                'flagpic' => 'uk.gif',
            ],
            12 => [
                'id' => 13,
                'name' => 'Ireland',
                'flagpic' => 'ireland.gif',
            ],
            13 => [
                'id' => 14,
                'name' => 'Poland',
                'flagpic' => 'poland.gif',
            ],
            14 => [
                'id' => 15,
                'name' => 'Netherlands',
                'flagpic' => 'netherlands.gif',
            ],
            15 => [
                'id' => 16,
                'name' => 'Belgium',
                'flagpic' => 'belgium.gif',
            ],
            16 => [
                'id' => 17,
                'name' => 'Japan',
                'flagpic' => 'japan.gif',
            ],
            17 => [
                'id' => 18,
                'name' => 'Brazil',
                'flagpic' => 'brazil.gif',
            ],
            18 => [
                'id' => 19,
                'name' => 'Argentina',
                'flagpic' => 'argentina.gif',
            ],
            19 => [
                'id' => 20,
                'name' => 'Australia',
                'flagpic' => 'australia.gif',
            ],
            20 => [
                'id' => 21,
                'name' => 'New Zealand',
                'flagpic' => 'newzealand.gif',
            ],
            21 => [
                'id' => 23,
                'name' => 'Spain',
                'flagpic' => 'spain.gif',
            ],
            22 => [
                'id' => 24,
                'name' => 'Portugal',
                'flagpic' => 'portugal.gif',
            ],
            23 => [
                'id' => 25,
                'name' => 'Mexico',
                'flagpic' => 'mexico.gif',
            ],
            24 => [
                'id' => 26,
                'name' => 'Singapore',
                'flagpic' => 'singapore.gif',
            ],
            25 => [
                'id' => 70,
                'name' => 'India',
                'flagpic' => 'india.gif',
            ],
            26 => [
                'id' => 65,
                'name' => 'Albania',
                'flagpic' => 'albania.gif',
            ],
            27 => [
                'id' => 29,
                'name' => 'South Africa',
                'flagpic' => 'southafrica.gif',
            ],
            28 => [
                'id' => 30,
                'name' => 'South Korea',
                'flagpic' => 'southkorea.gif',
            ],
            29 => [
                'id' => 31,
                'name' => 'Jamaica',
                'flagpic' => 'jamaica.gif',
            ],
            30 => [
                'id' => 32,
                'name' => 'Luxembourg',
                'flagpic' => 'luxembourg.gif',
            ],
            31 => [
                'id' => 34,
                'name' => 'Belize',
                'flagpic' => 'belize.gif',
            ],
            32 => [
                'id' => 35,
                'name' => 'Algeria',
                'flagpic' => 'algeria.gif',
            ],
            33 => [
                'id' => 36,
                'name' => 'Angola',
                'flagpic' => 'angola.gif',
            ],
            34 => [
                'id' => 37,
                'name' => 'Austria',
                'flagpic' => 'austria.gif',
            ],
            35 => [
                'id' => 38,
                'name' => 'Yugoslavia',
                'flagpic' => 'yugoslavia.gif',
            ],
            36 => [
                'id' => 39,
                'name' => 'Western Samoa',
                'flagpic' => 'westernsamoa.gif',
            ],
            37 => [
                'id' => 40,
                'name' => 'Malaysia',
                'flagpic' => 'malaysia.gif',
            ],
            38 => [
                'id' => 41,
                'name' => 'Dominican Republic',
                'flagpic' => 'dominicanrep.gif',
            ],
            39 => [
                'id' => 42,
                'name' => 'Greece',
                'flagpic' => 'greece.gif',
            ],
            40 => [
                'id' => 43,
                'name' => 'Guatemala',
                'flagpic' => 'guatemala.gif',
            ],
            41 => [
                'id' => 44,
                'name' => 'Israel',
                'flagpic' => 'israel.gif',
            ],
            42 => [
                'id' => 45,
                'name' => 'Pakistan',
                'flagpic' => 'pakistan.gif',
            ],
            43 => [
                'id' => 46,
                'name' => 'Czech Republic',
                'flagpic' => 'czechrep.gif',
            ],
            44 => [
                'id' => 47,
                'name' => 'Serbia',
                'flagpic' => 'serbia.gif',
            ],
            45 => [
                'id' => 48,
                'name' => 'Seychelles',
                'flagpic' => 'seychelles.gif',
            ],
            46 => [
                'id' => 50,
                'name' => 'Puerto Rico',
                'flagpic' => 'puertorico.gif',
            ],
            47 => [
                'id' => 51,
                'name' => 'Chile',
                'flagpic' => 'chile.gif',
            ],
            48 => [
                'id' => 52,
                'name' => 'Cuba',
                'flagpic' => 'cuba.gif',
            ],
            49 => [
                'id' => 53,
                'name' => 'Congo',
                'flagpic' => 'congo.gif',
            ],
            50 => [
                'id' => 54,
                'name' => 'Afghanistan',
                'flagpic' => 'afghanistan.gif',
            ],
            51 => [
                'id' => 55,
                'name' => 'Turkey',
                'flagpic' => 'turkey.gif',
            ],
            52 => [
                'id' => 56,
                'name' => 'Uzbekistan',
                'flagpic' => 'uzbekistan.gif',
            ],
            53 => [
                'id' => 57,
                'name' => 'Switzerland',
                'flagpic' => 'switzerland.gif',
            ],
            54 => [
                'id' => 58,
                'name' => 'Kiribati',
                'flagpic' => 'kiribati.gif',
            ],
            55 => [
                'id' => 59,
                'name' => 'Philippines',
                'flagpic' => 'philippines.gif',
            ],
            56 => [
                'id' => 60,
                'name' => 'Burkina Faso',
                'flagpic' => 'burkinafaso.gif',
            ],
            57 => [
                'id' => 61,
                'name' => 'Nigeria',
                'flagpic' => 'nigeria.gif',
            ],
            58 => [
                'id' => 62,
                'name' => 'Iceland',
                'flagpic' => 'iceland.gif',
            ],
            59 => [
                'id' => 63,
                'name' => 'Nauru',
                'flagpic' => 'nauru.gif',
            ],
            60 => [
                'id' => 64,
                'name' => 'Slovenia',
                'flagpic' => 'slovenia.gif',
            ],
            61 => [
                'id' => 66,
                'name' => 'Turkmenistan',
                'flagpic' => 'turkmenistan.gif',
            ],
            62 => [
                'id' => 67,
                'name' => 'Bosnia Herzegovina',
                'flagpic' => 'bosniaherzegovina.gif',
            ],
            63 => [
                'id' => 68,
                'name' => 'Andorra',
                'flagpic' => 'andorra.gif',
            ],
            64 => [
                'id' => 69,
                'name' => 'Lithuania',
                'flagpic' => 'lithuania.gif',
            ],
            65 => [
                'id' => 71,
                'name' => 'Netherlands Antilles',
                'flagpic' => 'nethantilles.gif',
            ],
            66 => [
                'id' => 72,
                'name' => 'Ukraine',
                'flagpic' => 'ukraine.gif',
            ],
            67 => [
                'id' => 73,
                'name' => 'Venezuela',
                'flagpic' => 'venezuela.gif',
            ],
            68 => [
                'id' => 74,
                'name' => 'Hungary',
                'flagpic' => 'hungary.gif',
            ],
            69 => [
                'id' => 75,
                'name' => 'Romania',
                'flagpic' => 'romania.gif',
            ],
            70 => [
                'id' => 76,
                'name' => 'Vanuatu',
                'flagpic' => 'vanuatu.gif',
            ],
            71 => [
                'id' => 77,
                'name' => 'Vietnam',
                'flagpic' => 'vietnam.gif',
            ],
            72 => [
                'id' => 78,
                'name' => 'Trinidad & Tobago',
                'flagpic' => 'trinidadandtobago.gif',
            ],
            73 => [
                'id' => 79,
                'name' => 'Honduras',
                'flagpic' => 'honduras.gif',
            ],
            74 => [
                'id' => 80,
                'name' => 'Kyrgyzstan',
                'flagpic' => 'kyrgyzstan.gif',
            ],
            75 => [
                'id' => 81,
                'name' => 'Ecuador',
                'flagpic' => 'ecuador.gif',
            ],
            76 => [
                'id' => 82,
                'name' => 'Bahamas',
                'flagpic' => 'bahamas.gif',
            ],
            77 => [
                'id' => 83,
                'name' => 'Peru',
                'flagpic' => 'peru.gif',
            ],
            78 => [
                'id' => 84,
                'name' => 'Cambodia',
                'flagpic' => 'cambodia.gif',
            ],
            79 => [
                'id' => 85,
                'name' => 'Barbados',
                'flagpic' => 'barbados.gif',
            ],
            80 => [
                'id' => 86,
                'name' => 'Bangladesh',
                'flagpic' => 'bangladesh.gif',
            ],
            81 => [
                'id' => 87,
                'name' => 'Laos',
                'flagpic' => 'laos.gif',
            ],
            82 => [
                'id' => 88,
                'name' => 'Uruguay',
                'flagpic' => 'uruguay.gif',
            ],
            83 => [
                'id' => 89,
                'name' => 'Antigua Barbuda',
                'flagpic' => 'antiguabarbuda.gif',
            ],
            84 => [
                'id' => 90,
                'name' => 'Paraguay',
                'flagpic' => 'paraguay.gif',
            ],
            85 => [
                'id' => 93,
                'name' => 'Thailand',
                'flagpic' => 'thailand.gif',
            ],
            86 => [
                'id' => 92,
                'name' => 'Union of Soviet Socialist Republics',
                'flagpic' => 'ussr.gif',
            ],
            87 => [
                'id' => 94,
                'name' => 'Senegal',
                'flagpic' => 'senegal.gif',
            ],
            88 => [
                'id' => 95,
                'name' => 'Togo',
                'flagpic' => 'togo.gif',
            ],
            89 => [
                'id' => 96,
                'name' => 'North Korea',
                'flagpic' => 'northkorea.gif',
            ],
            90 => [
                'id' => 97,
                'name' => 'Croatia',
                'flagpic' => 'croatia.gif',
            ],
            91 => [
                'id' => 98,
                'name' => 'Estonia',
                'flagpic' => 'estonia.gif',
            ],
            92 => [
                'id' => 99,
                'name' => 'Colombia',
                'flagpic' => 'colombia.gif',
            ],
            93 => [
                'id' => 100,
                'name' => 'Lebanon',
                'flagpic' => 'lebanon.gif',
            ],
            94 => [
                'id' => 101,
                'name' => 'Latvia',
                'flagpic' => 'latvia.gif',
            ],
            95 => [
                'id' => 102,
                'name' => 'Costa Rica',
                'flagpic' => 'costarica.gif',
            ],
            96 => [
                'id' => 103,
                'name' => 'Egypt',
                'flagpic' => 'egypt.gif',
            ],
            97 => [
                'id' => 104,
                'name' => 'Bulgaria',
                'flagpic' => 'bulgaria.gif',
            ],
            98 => [
                'id' => 105,
                'name' => 'Isla de Muerte',
                'flagpic' => 'jollyroger.gif',
            ],
            99 => [
                'id' => 107,
                'name' => 'Pirates',
                'flagpic' => 'jollyroger.gif',
            ],
        ]);

    }
}
