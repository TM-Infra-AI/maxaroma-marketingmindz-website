<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Message extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public $attr = [];
    
    public function __construct($attr=[])
    {
        $this->attr = $attr;    
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.message');
    }
}
