<?php

namespace Database\Seeders;

use Domains\Cards\Models\RewardProgram;
use Illuminate\Database\Seeder;

class RewardProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programs = [
            [
                'name' => 'Nenhum Programa',
                'code' => null,
                'description' => 'Nenhum programa de fidelidade associado',
                'website' => null,
                'logo_path' => null,
            ],
            [
                'name' => 'Smiles',
                'code' => 'SMILES',
                'description' => 'Programa de fidelidade da Gol Linhas Aéreas',
                'website' => 'https://www.smiles.com.br',
                'logo_path' => 'logos/smiles.png',
            ],
            [
                'name' => 'Latam Pass',
                'code' => 'LATAMPASS',
                'description' => 'Programa de fidelidade da Latam Airlines',
                'website' => 'https://www.latampass.latam.com',
                'logo_path' => 'logos/latampass.png',
            ],
            [
                'name' => 'Livelo',
                'code' => 'LIVELO',
                'description' => 'Programa de pontos independente',
                'website' => 'https://www.livelo.com.br',
                'logo_path' => 'logos/livelo.png',
            ],
            [
                'name' => 'TudoAzul',
                'code' => 'TUDOAZUL',
                'description' => 'Programa de fidelidade da Azul Linhas Aéreas',
                'website' => 'https://www.voeazul.com.br/tudoazul',
                'logo_path' => 'logos/tudoazul.png',
            ],
            [
                'name' => 'Esfera',
                'code' => 'ESFERA',
                'description' => 'Programa de pontos do Santander',
                'website' => 'https://www.esfera.com.vc',
                'logo_path' => 'logos/esfera.png',
            ],
            [
                'name' => 'Dotz',
                'code' => 'DOTZ',
                'description' => 'Programa de pontos independente',
                'website' => 'https://www.dotz.com.br',
                'logo_path' => 'logos/dotz.png',
            ],
            [
                'name' => 'Prio',
                'code' => 'PRIO',
                'description' => 'Programa de fidelidade da rede de postos Ipiranga',
                'website' => 'https://www.prio.com.br',
                'logo_path' => 'logos/prio.png',
            ]
        ];

        foreach ($programs as $program) {
            RewardProgram::create($program);
        }
    }
}