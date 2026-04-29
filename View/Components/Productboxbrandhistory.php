<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Productboxbrandhistory extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
	public $ProductDetails = [];
    
	public function __construct($ProductDetails=[])
    {
		$this->ProductDetails = $ProductDetails;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.productboxbrandhistory');
    }
}
