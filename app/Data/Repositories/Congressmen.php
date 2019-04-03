<?php

namespace App\Data\Repositories;

use App\Data\Models\Congressman;
use App\Data\Models\CongressmanLegislature;
use PragmaRX\Coollection\Package\Coollection;

class Congressmen extends Repository
{
    /**
     * @var string
     */
    protected $model = Congressman::class;

    private function createCongressmanFromRemote($congressman)
    {
        return $this->firstOrCreate(
            [
                'remote_id' => $congressman['ID'],
            ],
            [
                'name' => ($name = $this->normalizeName($congressman['Nome'])),

                'nickname' =>
                    $this->normalizeName($congressman['NomePolitico']) ?? $name,

                'party_id' => $this->findParty($congressman['SiglaPartido'])
                    ->id,

                'photo_url' => $congressman['Foto'],

                'thumbnail_url' => $congressman['FotoPequena'],
            ]
        );
    }

    private function findParty($party)
    {
        return app(Parties::class)->findByCode(
            $this->normalizePartyCode($party)
        );
    }

    /**
     * @param $name
     * @return string|null
     */
    protected function normalizeName(string $name)
    {
        return filled($name = trim($name)) ? $name : null;
    }

    /**
     * @param $party
     * @return string
     */
    private function normalizePartyCode($party)
    {
        switch ($party) {
            case 'Novo':
                return 'NOVO';
            case 'PC do B':
                return 'PCdoB';
            case 'Patriota':
                return 'PATRI';
            case 'Avante':
                return 'AVANTE';
        }

        return $party;
    }

    /**
     * @param $data
     */
    public function sync(Coollection $data)
    {
        $data->each(function ($congressman) {
            $congressman = $this->createCongressmanFromRemote($congressman);

            if ($congressman->wasRecentlyCreated) {
                $legislature = CongressmanLegislature::firstOrCreate(
                    [
                        'congressman_id' => $congressman->id,
                        'legislature_id' => app(
                            Legislatures::class
                        )->getCurrent()->id,
                    ],
                    ['started_at' => now()]
                );

                $congressman->legislatures()->save($legislature);
            }
        });
    }
}
