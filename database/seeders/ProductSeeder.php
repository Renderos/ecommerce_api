<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['sku' => 'ELEC-001', 'name' => 'Audífonos Bluetooth Pro', 'category' => 'Electrónica', 'price' => 79.90, 'stock' => 50, 'description' => 'Audífonos inalámbricos con cancelación activa de ruido y 30 horas de batería.'],
            ['sku' => 'ELEC-002', 'name' => 'Teclado Mecánico RGB', 'category' => 'Electrónica', 'price' => 119.00, 'stock' => 30, 'description' => 'Teclado mecánico con switches rojos, retroiluminación RGB y layout en español.'],
            ['sku' => 'ELEC-003', 'name' => 'Mouse Inalámbrico Ergonómico', 'category' => 'Electrónica', 'price' => 39.50, 'stock' => 80, 'description' => 'Mouse ergonómico de 6 botones con sensor de 4000 DPI.'],
            ['sku' => 'ELEC-004', 'name' => 'Monitor 27" QHD', 'category' => 'Electrónica', 'price' => 329.99, 'stock' => 15, 'description' => 'Monitor IPS 27 pulgadas, resolución 2560x1440 a 144Hz.'],
            ['sku' => 'HOG-001', 'name' => 'Cafetera de Émbolo', 'category' => 'Hogar', 'price' => 24.90, 'stock' => 60, 'description' => 'Cafetera francesa de vidrio borosilicato, 1 litro.'],
            ['sku' => 'HOG-002', 'name' => 'Lámpara de Escritorio LED', 'category' => 'Hogar', 'price' => 45.00, 'stock' => 40, 'description' => 'Lámpara LED regulable con puerto de carga USB.'],
            ['sku' => 'DEP-001', 'name' => 'Botella Térmica 750ml', 'category' => 'Deportes', 'price' => 19.99, 'stock' => 120, 'description' => 'Botella de acero inoxidable que mantiene el frío 24h y el calor 12h.'],
            ['sku' => 'DEP-002', 'name' => 'Mat de Yoga Antideslizante', 'category' => 'Deportes', 'price' => 34.90, 'stock' => 45, 'description' => 'Mat de 6mm de espesor con correa de transporte.'],
            ['sku' => 'ROP-001', 'name' => 'Polerón Unisex Algodón', 'category' => 'Ropa', 'price' => 49.90, 'stock' => 70, 'description' => 'Polerón con capucha 100% algodón orgánico.'],
            ['sku' => 'ROP-002', 'name' => 'Zapatillas Running Light', 'category' => 'Ropa', 'price' => 89.90, 'stock' => 0, 'description' => 'Zapatillas livianas para running (sin stock, útil para probar validaciones).'],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['sku' => $data['sku']],
                $data + ['image_url' => 'https://placehold.co/600x400?text='.urlencode($data['name']), 'is_active' => true]
            );
        }
    }
}
