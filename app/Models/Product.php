<?php

namespace App\Models;

use App\Enums\ProductUnit as ProductUnitEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /** أنواع الأصناف (مستوحاة من الأصيل: بضاعة/خدمة/تجميعي) */
    public const TYPE_GOODS   = 'goods';
    public const TYPE_SERVICE = 'service';
    public const TYPE_BUNDLE  = 'bundle';

    public static array $types = [
        self::TYPE_GOODS   => 'بضاعة',
        self::TYPE_SERVICE => 'خدمة',
        self::TYPE_BUNDLE  => 'تجميعي',
    ];

    protected $fillable = [
        'name',
        'barcode',
        'category_id',
        'product_type',
        'product_class',
        'description',
        'cost_price',
        'selling_price',
        'is_taxable',
        'vat_rate',
        'bonus_after_qty',
        'bonus_every_qty',
        'bonus_free_qty',
        'currency_id',
        'cost_price_fc',
        'selling_price_fc',
        'quantity',
        'min_quantity',
        'max_quantity',
        'reorder_level',
        'unit',
        'allow_fractions',
        'expiry_date',
        'image',
        'inventory_account_id',
        'cogs_account_id'
    ];

    protected $casts = [
        'expiry_date'      => 'date',
        'cost_price'       => 'decimal:2',
        'selling_price'    => 'decimal:2',
        'quantity'         => 'decimal:3',
        'min_quantity'     => 'decimal:3',
        'max_quantity'     => 'decimal:3',
        'reorder_level'    => 'decimal:3',
        'is_taxable'       => 'boolean',
        'vat_rate'         => 'decimal:2',
        'bonus_after_qty'  => 'decimal:3',
        'bonus_every_qty'  => 'decimal:3',
        'bonus_free_qty'   => 'decimal:3',
        'cost_price_fc'    => 'decimal:4',
        'selling_price_fc' => 'decimal:4',
        'allow_fractions'  => 'boolean',
        'unit'             => ProductUnitEnum::class,
    ];

    // ── علاقات ───────────────────────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function costHistory()
    {
        return $this->hasMany(CostPriceHistory::class)->latest();
    }

    /** الوحدات الإضافية (كرتون/دستة...) */
    public function units()
    {
        return $this->hasMany(ProductUnit::class);
    }

    /** مكونات التصنيع/التجميع لهذا الصنف */
    public function components()
    {
        return $this->hasMany(ProductComponent::class, 'product_id');
    }

    /** الأصناف الأب التي يدخل هذا الصنف في تكوينها */
    public function usedIn()
    {
        return $this->hasMany(ProductComponent::class, 'component_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // ── نوع الصنف ────────────────────────────────────────────────────────────

    public function isService(): bool
    {
        return $this->product_type === self::TYPE_SERVICE;
    }

    public function isBundle(): bool
    {
        return $this->product_type === self::TYPE_BUNDLE;
    }

    /** هل يُتتبَّع مخزونه؟ (الخدمة والتجميعي لا كارت مخزن لهما) */
    public function tracksStock(): bool
    {
        return $this->product_type === self::TYPE_GOODS || $this->product_type === null;
    }

    public function typeLabel(): string
    {
        return self::$types[$this->product_type] ?? 'بضاعة';
    }

    // ── فئة المخزون المحاسبية (IAS 2 §37) ────────────────────────────────────

    public const CLASS_MERCHANDISE  = 'merchandise';   // بضاعة تجارية
    public const CLASS_RAW          = 'raw_material';   // مواد خام
    public const CLASS_MANUFACTURED = 'manufactured';   // منتج تام الصنع
    public const CLASS_SERVICE      = 'service';         // خدمة

    public static array $classes = [
        self::CLASS_MERCHANDISE  => 'بضاعة تجارية',
        self::CLASS_RAW          => 'مادة خام',
        self::CLASS_MANUFACTURED => 'منتج تام الصنع',
        self::CLASS_SERVICE      => 'خدمة',
    ];

    public function classLabel(): string
    {
        return self::$classes[$this->product_class] ?? 'بضاعة تجارية';
    }

    public function isManufactured(): bool
    {
        return $this->product_class === self::CLASS_MANUFACTURED || $this->product_type === self::TYPE_BUNDLE;
    }

    /** مفتاح إعداد حساب المخزون المناسب لفئة الصنف */
    public function inventorySettingKeyForClass(): ?string
    {
        return match ($this->product_class) {
            self::CLASS_RAW          => 'account_inventory_raw_code',
            self::CLASS_MANUFACTURED => 'account_inventory_finished_code',
            self::CLASS_MERCHANDISE  => 'account_inventory_code',
            default                  => null, // خدمة: بلا مخزون
        };
    }

    /** الأصناف (بضاعة + مواد خام) — للفصل في الشاشات */
    public function scopeItems($q)
    {
        return $q->whereIn('product_class', [self::CLASS_MERCHANDISE, self::CLASS_RAW]);
    }

    /** المنتجات المصنّعة (تامة الصنع) */
    public function scopeManufactured($q)
    {
        return $q->where('product_class', self::CLASS_MANUFACTURED)
                 ->orWhere('product_type', self::TYPE_BUNDLE);
    }

    protected static function boot(): void
    {
        parent::boot();

        // ربط حساب المخزون تلقائياً حسب الفئة إن لم يُحدَّد يدوياً (ترحيل محاسبي صحيح)
        static::saving(function (Product $p) {
            if (empty($p->inventory_account_id) && $key = $p->inventorySettingKeyForClass()) {
                $code = \App\Models\Setting::get($key);
                if ($code) {
                    $acc = \App\Models\Account::where('code', $code)->where('is_active', true)->first();
                    if ($acc && !$acc->is_header) {
                        $p->inventory_account_id = $acc->id;
                    }
                }
            }
        });
    }

    // ── الضريبة لكل صنف ──────────────────────────────────────────────────────

    /**
     * نسبة الضريبة الفعلية للصنف:
     * غير خاضع → 0، نسبة خاصة → نسبته، وإلا → النسبة العامة من الإعدادات.
     */
    public function effectiveVatRate(): float
    {
        if (!(bool) Setting::get('vat_enabled', 0)) {
            return 0.0;
        }
        if (!$this->is_taxable) {
            return 0.0;
        }
        if ($this->vat_rate !== null) {
            return (float) $this->vat_rate;
        }
        return (float) Setting::get('vat_rate', 15);
    }

    // ── البونص (الكمية الإضافية) ─────────────────────────────────────────────

    /**
     * الكمية المجانية المستحقة لكمية مبيعة:
     * بعد بلوغ "إضافي بعد الكمية"، يُمنح "الكمية المجانية" عن كل "إضافي كل كمية".
     */
    public function bonusFor(float $qty): float
    {
        $after = (float) ($this->bonus_after_qty ?? 0);
        $every = (float) ($this->bonus_every_qty ?? 0);
        $free  = (float) ($this->bonus_free_qty ?? 0);

        if ($every <= 0 || $free <= 0 || $qty < $after || $qty <= 0) {
            return 0.0;
        }
        return floor($qty / $every) * $free;
    }

    // ── المخزون والحدود ──────────────────────────────────────────────────────

    public function isLowStock()
    {
        return $this->tracksStock() && $this->quantity <= $this->min_quantity;
    }

    /** بلغ حد إعادة الطلب؟ */
    public function needsReorder(): bool
    {
        return $this->tracksStock()
            && $this->reorder_level !== null
            && (float) $this->quantity <= (float) $this->reorder_level;
    }

    /** تجاوز الحد الأقصى للتخزين؟ */
    public function isOverMax(): bool
    {
        return $this->tracksStock()
            && $this->max_quantity !== null
            && (float) $this->max_quantity > 0
            && (float) $this->quantity > (float) $this->max_quantity;
    }

    public function isExpiringSoon($days = 30)
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->diffInDays(now()) <= $days && $this->expiry_date->isFuture();
    }

    public function isExpired()
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->isPast();
    }

    // ── التجميعي: تكلفة المكونات ────────────────────────────────────────────

    /** تكلفة وحدة واحدة من الصنف التجميعي/المصنّع = Σ(كمية المكوّن × تكلفته) */
    public function componentsCost(): float
    {
        return round(
            $this->components()->with('component:id,cost_price')->get()
                ->sum(fn($c) => (float) $c->quantity * (float) ($c->component->cost_price ?? 0)),
            4
        );
    }
}
