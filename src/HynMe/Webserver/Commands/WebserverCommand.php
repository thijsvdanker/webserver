<?php namespace HynMe\Webserver\Commands;

use App\Commands\Command;

use App;

use HynMe\Webserver\Generators\Unix\WebsiteUser;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldBeQueued;

use HynMe\Webserver\Generators\Webserver\Fpm;
use HynMe\Webserver\Generators\Webserver\Nginx;

class WebserverCommand extends Command implements SelfHandling, ShouldBeQueued {

	use
        InteractsWithQueue;

    /**
     * @var Website
     */
    protected $website;

    /**
     * @var string
     */
    protected $action;

    /**
     * Create a new command instance.
     *
     * @param int                       $website_id
     * @param string                    $action
     */
	public function __construct($website_id, $action = 'update')
	{
		$this->website = App::make('HynMe\MultiTenant\Contracts\WebsiteRepositoryContract')->findById($website_id);
        $this->action = $action;
	}

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle()
	{
        if(!in_array($this->action, ['create', 'update', 'delete']))
            return;

        $action = sprintf("on%s", ucfirst($this->action));

        (new WebsiteUser($this->website))->{$action}();
        (new Nginx($this->website))->{$action}();
        (new Fpm($this->website))->{$action}();
	}

}