<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Filtersearch extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
	public $filterData = [];
	public $filterAttr = [];
	public $filterSelected = [];
    
	public function __construct($filterData=[],$filterAttr=[],$filterSelected=[])
    {
		$this->filterData = $filterData;		
		$this->filterAttr = $filterAttr;
		$this->filterSelected = $filterSelected;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.filtersearch');
    }
}
