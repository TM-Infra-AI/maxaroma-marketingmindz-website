<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Productboxsearch extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
	public $prodData = [];
	public $sliderClass = '';
    
	public function __construct($prodData=[],$sliderClass='')
    {
		$this->prodData = $prodData;		
		$this->sliderClass = $sliderClass;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.productboxsearch');
    }
}
