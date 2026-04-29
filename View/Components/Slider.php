<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Slider extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
	public $attr = [];
	public $sliderData = [];
    public $type = "";
    public $sliderLink = "";
    
	public function __construct($attr=[],$sliderData=[],$type='',$sliderLink='')
    {
		$this->attr = $attr;
		$this->sliderData = $sliderData;
        $this->type = $type;
        $this->sliderLink = $sliderLink;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.slider');
    }
}
