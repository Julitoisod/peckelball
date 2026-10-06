<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Shop extends BaseController
{
    public function index(): string
    {
        $category = (string) ($this->request->getGet('c') ?? '');
        $query    = $this->db->table('products')->where('is_active', 1);

        $categories = array_column(
            $this->db->table('products')->select('category, COUNT(*) AS n')->where('is_active', 1)->groupBy('category')->get()->getResultArray(),
            'n',
            'category'
        );

        if ($category !== '' && isset($categories[$category])) {
            $query->where('category', $category);
        } else {
            $category = '';
        }

        return $this->page('pages/shop', [
            'title'    => 'Pro shop | Go Paddle',
            'meta'     => 'Paddles, balls, apparel and accessories at the Go Paddle pro shop in Trento. Reserve online, pay at the desk.',
            'products' => $query->orderBy('is_featured', 'DESC')->orderBy('name')->get()->getResultArray(),
            'categories' => $categories,
            'active'   => $category,
        ]);
    }

    public function show(string $slug): string
    {
        $product = $this->db->table('products')->where('slug', $slug)->where('is_active', 1)->get()->getRowArray();
        if ($product === null) {
            throw PageNotFoundException::forPageNotFound('No product by that name.');
        }

        return $this->page('pages/product_detail', [
            'title'   => $product['name'] . ' | Go Paddle pro shop',
            'meta'    => mb_strimwidth((string) $product['description'], 0, 155, '…'),
            'product' => $product,
            'related' => $this->db->table('products')
                ->where('category', $product['category'])->where('id !=', $product['id'])
                ->where('is_active', 1)->limit(3)->get()->getResultArray(),
        ]);
    }
}
