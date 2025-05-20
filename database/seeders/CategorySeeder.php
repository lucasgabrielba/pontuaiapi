<?php

namespace Database\Seeders;

use Domains\Finance\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Array com as categorias usando ícones do Lucide
        $categories = [
            [
                'name' => 'Alimentação',
                'code' => 'FOOD',
                'description' => 'Restaurantes, delivery e supermercados',
                'icon' => 'utensils',
                'color' => 'red'
            ],
            [
                'name' => 'Supermercado',
                'code' => 'SUPER',
                'description' => 'Compras em supermercados e mercearias',
                'icon' => 'shopping-cart',
                'color' => 'yellow'
            ],
            [
                'name' => 'Transporte',
                'code' => 'TRANS',
                'description' => 'Uber, táxi, ônibus, combustível e pedágio',
                'icon' => 'car',
                'color' => 'green'
            ],
            [
                'name' => 'Combustível',
                'code' => 'FUEL',
                'description' => 'Postos de gasolina',
                'icon' => 'fuel',
                'color' => 'orange'
            ],
            [
                'name' => 'Streaming',
                'code' => 'STREAM',
                'description' => 'Netflix, Spotify, Disney+, entre outros',
                'icon' => 'play',
                'color' => 'red'
            ],
            [
                'name' => 'Farmácia',
                'code' => 'PHARM',
                'description' => 'Medicamentos e produtos de cuidados pessoais',
                'icon' => 'pill',
                'color' => 'blue'
            ],
            [
                'name' => 'E-commerce',
                'code' => 'ECOMM',
                'description' => 'Compras online',
                'icon' => 'shopping-bag',
                'color' => 'purple'
            ],
            [
                'name' => 'Delivery',
                'code' => 'DELIV',
                'description' => 'Serviços de entrega de comida',
                'icon' => 'package',
                'color' => 'pink'
            ],
            [
                'name' => 'Educação',
                'code' => 'EDU',
                'description' => 'Cursos, livros e material escolar',
                'icon' => 'graduation-cap',
                'color' => 'cyan'
            ],
            [
                'name' => 'Saúde',
                'code' => 'HEALTH',
                'description' => 'Médicos, exames e planos de saúde',
                'icon' => 'heart-pulse',
                'color' => 'teal'
            ],
            [
                'name' => 'Lazer',
                'code' => 'LEISURE',
                'description' => 'Cinema, teatro, shows e eventos',
                'icon' => 'film',
                'color' => 'indigo'
            ],
            [
                'name' => 'Viagem',
                'code' => 'TRAVEL',
                'description' => 'Hospedagem, passagens e pacotes',
                'icon' => 'plane',
                'color' => 'cyan'
            ],
            [
                'name' => 'Vestuário',
                'code' => 'CLOTH',
                'description' => 'Roupas, calçados e acessórios',
                'icon' => 'shirt',
                'color' => 'gray'
            ],
            [
                'name' => 'Assinaturas',
                'code' => 'SUBS',
                'description' => 'Assinaturas e mensalidades diversas',
                'icon' => 'calendar-check',
                'color' => 'violet'
            ],
            [
                'name' => 'Casa',
                'code' => 'HOME',
                'description' => 'Aluguel, condomínio, luz, água e internet',
                'icon' => 'home',
                'color' => 'rose'
            ],
            [
                'name' => 'Diversão',
                'code' => 'FUN',
                'description' => 'Bares, baladas e festas',
                'icon' => 'wine',
                'color' => 'amber'
            ],
            [
                'name' => 'Pix',
                'code' => 'PIX',
                'description' => 'Transferências via Pix',
                'icon' => 'arrow-left-right',
                'color' => 'emerald'
            ],
            [
                'name' => 'Outros',
                'code' => 'OTHER',
                'description' => 'Transações não categorizadas',
                'icon' => 'help-circle',
                'color' => 'gray'
            ],
        ];

        // Criar as categorias no banco de dados
        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}