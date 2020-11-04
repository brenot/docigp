<?php

namespace App\Services\AnnualReport;

use App\Data\Models\Budget;
use App\Data\Models\Congressman;
use App\Data\Models\CongressmanBudget;
use App\Data\Models\CongressmanLegislature;
use App\Data\Models\CostCenter;
use App\Data\Models\Entry;
use App\Data\Models\Legislature;
use App\Support\Constants;
use Carbon\CarbonPeriod;
use HnhDigital\LaravelNumberConverter\Facade as NumConvert;

class Service
{
    public $spentTotal;
    public $creditTotal;
    public $creditCostCenter;
    public $refundTotal;
    public $refundCostCenter;
    public $legislature;
    public $period;
    public $costCentersRows;
    public $congressman;

    public function init($year = '2019', $congressman)
    {
        $this->spentTotal = 0;
        $this->creditTotal = 0;
        $this->creditCostCenter = CostCenter::where(
            'code',
            Constants::COST_CENTER_CREDIT_ID
        )->first();
        $this->refundTotal = 0;
        $this->refundCostCenter = CostCenter::where(
            'code',
            Constants::COST_CENTER_REFUND_CODE
        )->first();

        $this->legislature = Legislature::where('year_start', '<=', $year)
            ->where('year_end', '>=', $year)
            ->first();

        $this->congressman = $congressman;

        $this->period = CarbonPeriod::create(
            $year . '-01-01',
            '1 month',
            $year . '-12-01'
        );

        $this->costCentersRows = $this->costCenterTable();
    }

    public function fillFirstRow($table)
    {
        $row = collect([]);

        $row->push('');

        //Meses
        $row->push('JAN');
        $row->push('FEV');
        $row->push('MAR');
        $row->push('ABR');
        $row->push('MAI');
        $row->push('JUN');
        $row->push('JUL');
        $row->push('AGO');
        $row->push('SET');
        $row->push('OUT');
        $row->push('NOV');
        $row->push('DEZ');

        $row->push('TOTAL');

        $table->push($row);

        return $table;
    }

    public function fillPercentageRow($table)
    {
        $row = collect([]);
        $row->push('Solicitado(%)');
        foreach ($this->period as $month) {
            $budget = Budget::where('date', $month)->first();

            if ($budget) {
                $congressmanLegislature = CongressmanLegislature::where(
                    'congressman_id',
                    $this->congressman->id
                )
                    ->where('legislature_id', $this->legislature->id)
                    ->first();

                if ($congressmanLegislature) {
                    //                    dd($congressmanLegislature);

                    $congressmanBudget = CongressmanBudget::where(
                        'budget_id',
                        $budget->id
                    )
                        ->where(
                            'congressman_legislature_id',
                            $congressmanLegislature->id
                        )
                        ->first();

                    $row->push($congressmanBudget->percentage);

                    //Calcula crédito
                    Entry::where('cost_center_id', $this->creditCostCenter->id)
                        ->where('congressman_budget_id', $congressmanBudget->id)
                        ->get()
                        ->each(function ($item) {
                            $this->creditTotal += abs($item->value);
                        });

                    //Calcula devolução
                    Entry::where('cost_center_id', $this->refundCostCenter->id)
                        ->where('congressman_budget_id', $congressmanBudget->id)
                        ->get()
                        ->each(function ($item) {
                            $this->refundTotal += abs($item->value);
                        });
                }
            } else {
                $row->push('0.00');
            }
        }
        $row->push('');
        $table->push($row);

        return $table;
    }

    public function fillInsideRows($table)
    {
        foreach ($this->costCentersRows as $costCenter) {
            $row = collect([]);
            $total = 0;
            $row->push($costCenter['abbreviation']);

            foreach ($this->period as $month) {
                $budget = Budget::where('date', $month)->first();

                if ($budget) {
                    $congressmanLegislature = CongressmanLegislature::where(
                        'congressman_id',
                        $this->congressman->id
                    )
                        ->where('legislature_id', $this->legislature->id)
                        ->first();

                    if ($congressmanLegislature) {
                        //                    dd($congressmanLegislature);

                        $congressmanBudget = CongressmanBudget::where(
                            'budget_id',
                            $budget->id
                        )
                            ->where(
                                'congressman_legislature_id',
                                $congressmanLegislature->id
                            )
                            ->first();

                        $entries = Entry::selectRaw('sum(value) as soma')
                            ->where(
                                'congressman_budget_id',
                                $congressmanBudget->id
                            )
                            ->whereIn('cost_center_id', $costCenter['ids'])
                            ->first();

                        $total += abs($entries->soma);

                        $soma = number_format(abs($entries->soma), 2, '.', '');

                        $row->push($soma);
                    }
                } else {
                    $row->push('0.00');
                }
            }

            $this->spentTotal += $total;
            $row->push(number_format($total, 2, '.', ''));

            $table->push($row);
        }

        return $table;
    }

    public function fillTotalsRow($table)
    {
        $row = collect([]);
        $row->push('TOTAL');
        foreach ($this->period as $month) {
            $budget = Budget::where('date', $month)->first();

            if ($budget) {
                $congressmanLegislature = CongressmanLegislature::where(
                    'congressman_id',
                    $this->congressman->id
                )
                    ->where('legislature_id', $this->legislature->id)
                    ->first();

                if ($congressmanLegislature) {
                    $congressmanBudget = CongressmanBudget::where(
                        'budget_id',
                        $budget->id
                    )
                        ->where(
                            'congressman_legislature_id',
                            $congressmanLegislature->id
                        )
                        ->first();

                    $entries = Entry::selectRaw('sum(value) as soma');

                    $entries->orWhere(function ($query) {
                        foreach ($this->costCentersRows as $costCenter) {
                            $query->orWhereIn(
                                'cost_center_id',
                                $costCenter['ids']
                            );
                        }
                    });

                    $entries->where(
                        'congressman_budget_id',
                        $congressmanBudget->id
                    );

                    $total = abs($entries->first()->soma);

                    $row->push(number_format($total, 2, '.', ''));
                }
            } else {
                $row->push('0.00');
            }
        }
        $row->push('');
        $table->push($row);

        return $table;
    }

    public function getMainTable($year = '2019', $congressman)
    {
        //        $year = '2019';

        $this->init($year, $congressman);
        $table = collect([]);

        $table = $this->fillFirstRow($table);

        //Gerar segunda linha de percentual
        $table = $this->fillPercentageRow($table);
        //Fim da segunda linha

        //Gerar linhas do meio
        $table = $this->fillInsideRows($table);
        //Fim das linhas do meio

        //Gerar última linha
        $table = $this->fillTotalsRow($table);
        //Fim da última linha

        //        dump('creditTotal');
        //        dump($this->creditTotal);
        //        dump('refundTotal');
        //        dump($this->refundTotal);
        //        dump('spentTotal');
        //        dump($this->spentTotal);

        return [
            'congressman' => $this->congressman,
            'year' => $year,
            'mainTable' => $table,
            'totalsTable' => [
                'creditTotal' => $this->creditTotal,
                'refundTotal' => $this->refundTotal,
                'spentTotal' => $this->spentTotal,
                'spentAndRefundTotal' => $this->spentTotal + $this->refundTotal,
                'situation' =>
                    $this->creditTotal == $this->refundTotal + $this->spentTotal
                        ? 'REGULAR'
                        : 'IRREGULAR'
            ]
        ];
    }

    public function costCenterTable()
    {
        $abbreviations = [
            'I' => 'Passagens',
            'II' => 'Serv. Postais',
            'III' => 'Manut. Gab.',
            'IV' => 'Custeio Gab.',
            'V' => 'Alimentação',
            'VI.a' => 'Locomoção',
            'VI.b' => 'Loc. veículos',
            'VII' => 'Combustíveis',
            'VIII' => 'Divulgação',
            'IX' => 'Cursos',
            'X' => 'Diárias',
            'XI' => 'Tarifas'
        ];

        $allResponse = collect();

        $i = 1;
        while (
            $parent = CostCenter::where(
                'code',
                $roman = NumConvert::roman($i)
            )->first()
        ) {
            if ($i == 6) {
                $costCenters = CostCenter::where('parent_code', $roman)->get();

                $costCenters->each(function ($costCenter) use (
                    $abbreviations,
                    $allResponse,
                    $roman,
                    $i
                ) {
                    $costCenterArrayResponse = [
                        'abbreviation' =>
                            $abbreviations[$costCenter->code] ?? '',
                        'number' => $i,
                        'roman' => $costCenter->code,
                        'ids' => collect($costCenter->id)
                    ];

                    $allResponse->push($costCenterArrayResponse);
                });
            } else {
                $costCenterIds = CostCenter::where('code', $roman)
                    ->orWhere('parent_code', $roman)
                    ->get()
                    ->map(function ($item) {
                        return $item->id;
                    });

                $collection = collect($costCenterIds);

                $costCenterArrayResponse = [
                    'abbreviation' => $abbreviations[$roman] ?? '',
                    'number' => $i,
                    'roman' => $roman,
                    'ids' => $collection
                ];

                $allResponse->push($costCenterArrayResponse);
            }

            $i++;
        }

        return $allResponse;
    }
}