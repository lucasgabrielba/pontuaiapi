<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            [
                'name' => 'Banco do Brasil',
                'code' => '001',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2014/05/banco-do-brasil-logo.png',
                'primary_color' => '#FFEF38',
                'secondary_color' => '#0067B1',
                'description' => 'Banco do Brasil S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'Bradesco',
                'code' => '237',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2018/09/bradesco-logo.png',
                'primary_color' => '#CC092F',
                'secondary_color' => '#051244',
                'description' => 'Banco Bradesco S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'Caixa Econômica Federal',
                'code' => '104',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2016/10/caixa-logo.png',
                'primary_color' => '#0070AF',
                'secondary_color' => '#ED1C24',
                'description' => 'Caixa Econômica Federal',
                'is_active' => true,
            ],
            [
                'name' => 'Itaú',
                'code' => '341',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2016/10/itau-logo-1.png',
                'primary_color' => '#EC7000',
                'secondary_color' => '#003F8D',
                'description' => 'Itaú Unibanco S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'Santander',
                'code' => '033',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2016/10/Santander-logo.png',
                'primary_color' => '#EC0000',
                'secondary_color' => '#FFFFFF',
                'description' => 'Banco Santander (Brasil) S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'Nubank',
                'code' => '260',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2019/08/nubank-logo-1.png',
                'primary_color' => '#8A05BE',
                'secondary_color' => '#FFFFFF',
                'description' => 'Nu Pagamentos S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'Inter',
                'code' => '077',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2020/02/banco-inter-logo-1.png',
                'primary_color' => '#FF7A00',
                'secondary_color' => '#FFFFFF',
                'description' => 'Banco Inter S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'C6 Bank',
                'code' => '336',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2020/11/c6-bank-logo-1.png',
                'primary_color' => '#242424',
                'secondary_color' => '#FFFFFF',
                'description' => 'Banco C6 S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'Original',
                'code' => '212',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2020/11/banco-original-logo-1.png',
                'primary_color' => '#FDD200',
                'secondary_color' => '#000000',
                'description' => 'Banco Original S.A.',
                'is_active' => true,
            ],
            [
                'name' => 'PicPay',
                'code' => '380',
                'logo_url' => 'https://logodownload.org/wp-content/uploads/2018/05/picpay-logo.png',
                'primary_color' => '#21C25E',
                'secondary_color' => '#FFFFFF',
                'description' => 'PicPay Serviços S.A.',
                'is_active' => true,
            ],
        ];

        foreach ($banks as $bank) {
            DB::table('banks')->insert(array_merge(
                $bank,
                [
                    'id' => (string) Str::ulid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ));
        }
    }
}