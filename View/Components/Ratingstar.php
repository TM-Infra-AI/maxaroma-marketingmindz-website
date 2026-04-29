<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Ratingstar extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public $starRating;

    public function __construct($starRating = 0)
    {
        $this->starRating = $starRating;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.ratingstar');
    }
}
