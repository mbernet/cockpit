<?php
$app->on('admin.init', function() {
        $this('admin')->menu('top', [
            'url' => $this->routeUrl('/devices_registered'),
            'label' => '<i class="uk-icon-picture-o"></i>',
            'title' => $this('i18n')->get('Devices'),
            'active' => (strpos($this['route'], '/devices_registered') === 0)
        ], 5);
    }
);