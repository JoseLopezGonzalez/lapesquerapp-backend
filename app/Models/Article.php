<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Product;
use App\Models\ArticleCategory;
use Illuminate\Validation\ValidationException;

class Article extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    //protected $table = 'articles';

    protected $fillable = [
        'name',
        'category_id',
    ];

    public function product()
    {
        if ($this->categoria->name === 'product') { // Cambia el valor 1 por el ID de la categoría correspondiente
            return $this->hasOne(Product::class);
        }
        return null; // No hay relación
    }

    public function categoria()
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->categoria->toArrayAssoc(),
        ];
    }

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($article) {
            // Validar name no vacío
            if (empty($article->name)) {
                throw ValidationException::withMessages([
                    'name' => 'El nombre del artículo no puede estar vacío.',
                ]);
            }

            // Validar name único por tenant
            $existing = self::where('name', $article->name)
                ->where('id', '!=', $article->id ?? 0)
                ->first();
            
            if ($existing) {
                throw ValidationException::withMessages([
                    'name' => 'Ya existe un artículo con este nombre.',
                ]);
            }
        });
    }





}
