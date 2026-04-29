<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Button extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
	public $btype = '';
	public $name = '';
	public $btntext;
	public $link = '';
	public $classname;
	public $bsvg = [];
	public $btitle = "";
    public $bid = '';
	
    public function __construct($btype='',$name='',$btntext,$link='',$classname,$btitle='',$bsvg = [],$bid='')
    {
        $this->btype = $btype;
		$this->name = $name;
		$this->btntext = $btntext;	
		$this->link = $link;
		$this->classname = $classname;
		$this->bsvg = $bsvg;
		$this->btitle = $btitle;
        $this->bid = $bid;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.button');
    }
}

