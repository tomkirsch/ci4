<?php

namespace App\Controllers;

use App\Entities\Foo;

class Home extends BaseController
{
    public function index()
    {
        $foo = new Foo();
        $foo->fillNullEmpty([
            "foo_id" => 5,
            "foo_none" => "",
            "foo_name" => "bar",
        ]);
        print $foo->getHtmlData();
    }
}
