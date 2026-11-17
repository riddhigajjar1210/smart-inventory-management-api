<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockMovement extends Model
{
    /**
     * No updated_at: a movement is an immutable audit record.
     */
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'note',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'warehouse_id' => 'integer',
            'movement_type' => MovementType::class,
            'quantity' => 'integer',
            'reference_id' => 'integer',
            'moved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Stock movements are immutable and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Stock movements are immutable and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from !== null) {
            $query->where('moved_at', '>=', $from.' 00:00:00');
        }

        if ($to !== null) {
            $query->where('moved_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
