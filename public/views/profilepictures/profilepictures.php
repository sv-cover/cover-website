<?php

class ProfilepicturesView extends CRUDView
{
    public function scripts()
    {
        return array_merge(parent::scripts(), [
            get_theme_data('assets/dist/js/images.js'),
        ]);
    }
}
