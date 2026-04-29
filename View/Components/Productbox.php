<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Productbox extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
	public $prodData = [];
	public $sliderClass = '';
	public $page = '';
    
	public function __construct($prodData=[],$sliderClass='',$page = '')
    {
		$this->prodData = $prodData;		
		$this->sliderClass = $sliderClass;
		$this->page = $page; 
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.productbox');
    }
}
