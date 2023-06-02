<?php

namespace Comfino;

class Api_Client
{
    public function getNotifyUrl(): string
    {
        return get_rest_url(null, 'comfino/notification');
    }

    public function getConfigurationUrl()
    {
        return get_rest_url(null, 'comfino/configuration');
    }
}
