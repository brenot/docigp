<?php

namespace App\Data\Models;

use App\Data\Traits\ModelActionable;

class Entry extends Model
{
    use ModelActionable;

    protected $table = 'entries';

    /**
     * @var array
     */
    protected $fillable = [
        'date',
        'value',
        'object',
        'to',
        'provider_id',
        'cost_center_id',
        'congressman_budget_id',
        'verified_at',
        'analysed_at',
        'published_at',
        'verified_by_id',
        'analysed_by_id',
        'published_by_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected $dates = ['date', 'verified_at', 'analysed_at', 'published_at'];

    protected $selectColumns = [
        'entries.*',
        'cost_centers.name as cost_center_name',
        'cost_centers.code as cost_center_code',
        'providers.name as provider_name',
        'providers.cpf_cnpj as provider_cpf_cnpj',
        'providers.type as provider_type',
    ];

    protected $selectColumnsRaw = [
        '(select count(*) from entry_documents ed where ed.entry_id = entries.id) as documents_count',
        '(select count(*) from entry_documents ed where ed.entry_id = entries.id and ed.analysed_at is null) > 0 as has_pendency',
    ];

    protected $filterableColumns = [
        'entries.to',
        'entries.object',
        'entries.value',
    ];

    protected $orderBy = ['date' => 'desc'];

    protected $joins = [
        'providers' => ['providers.id', '=', 'entries.provider_id', 'left'],
        'cost_centers' => ['cost_centers.id', '=', 'entries.cost_center_id'],
    ];

    protected $updatingTransport = false;

    protected static $eventsEnabled = true;

    public static function boot()
    {
        parent::boot();

        static::updated(function (Entry $model) {
            if (static::$eventsEnabled) {
                $model->updateTransport();
            }
        });

        static::created(function (Entry $model) {
            if (static::$eventsEnabled) {
                $model->updateTransport();
            }
        });

        static::deleted(function (Entry $model) {
            if (static::$eventsEnabled) {
                $model->updateTransport();
            }
        });
    }

    public function documents()
    {
        return $this->hasMany(EntryDocument::class);
    }

    public function congressmanBudget()
    {
        return $this->belongsTo(CongressmanBudget::class);
    }

    public function file()
    {
        return $this->morphOne(AttachedFile::class, 'fileable');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function updateTransport()
    {
        if ($this->updatingTransport) {
            return;
        }

        $this->updatingTransport = true;

        $this->congressmanBudget->updateTransportEntries();

        $this->updatingTransport = false;
    }

    public static function disableEvents()
    {
        static::$eventsEnabled = false;
    }

    public static function enableEvents()
    {
        static::$eventsEnabled = true;
    }
}
