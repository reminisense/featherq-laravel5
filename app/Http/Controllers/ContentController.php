<?php

class ContentController extends BaseController {

    /*
    |--------------------------------------------------------------------------
    | Default Home Controller
    |--------------------------------------------------------------------------
    |
    | You may wish to use controllers instead of, or in addition to, Closure
    | based routes. That's great! Here is an example controller method to
    | get you started. To route to this controller, just add the route:
    |
    |	Route::get('/', 'HomeController@showWelcome');
    |
    */

    public function getMain()
    {
        return View::make('content.main');
    }

    public function getGuides()
    {
        return View::make('content.main-guides');
    }

    /*
     * BEGIN: Evergreen Articles
     */

    public function getCustomerTimePerception()
    {
        return View::make('content.evergreen01');
    }

    public function getJustInTime()
    {
        return View::make('content.evergreen02');
    }

    public function getSerpentineQueue()
    {
        return View::make('content.evergreen03');
    }

    public function getWhatMakesYouAnxious()
    {
        return View::make('content.evergreen04');
    }

    public function getWhyQueueManagementImportant()
    {
        return View::make('content.evergreen05');
    }

    public function getManagingLines()
    {
        return View::make('content.managing-lines');
    }

    public function getWhatIsQueuing()
    {
        return View::make('content.what-is-queuing');
    }

    /*
     * BEGIN: How-To Articles
     */

    public function getSmallRestaurants()
    {
        return View::make('content.guide-small-restaurants');
    }

    public function getUseQrCode()
    {
        return View::make('content.guide-qr-code');
    }

    public function getCallServeNext()
    {
        return View::make('content.guide-call-serve-next');
    }

}
