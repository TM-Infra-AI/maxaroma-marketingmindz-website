<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Productboxref extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public $prodData = [];

    public $productDetailsku = '';

    public $referencedProductclass = '';

    public $referencedProductevent= '';

    public function __construct($prodData = [], $productDetailsku = "", $referencedProductclass = "", $referencedProductevent)
    {
        $this->prodData = $prodData;
        $this->productDetailsku = $productDetailsku;
        $this->referencedProductclass = $referencedProductclass;
        $this->referencedProductevent = $referencedProductevent;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.productboxref');
    }
}
