<?php
class GearmanNode extends AppInstance {

	public $client;
	public $worker;
	public $interval;

	/**
	 * @method init
	 * @description Constructor.
	 * @return void
	 */
	public function init() {
		$this->defaultConfig(array(
			'servers' => '127.0.0.1',
			'port'    => 4730,
			'enable'  => 0,
		));

		if ($this->config->enable->value) {
			Daemon::log(__CLASS__ . ' up.');
   
			$this->client = new GearmanClient;

			$this->worker = new GearmanWorker;
			$this->worker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
			$this->worker->setTimeout(0);

			foreach (explode(',', $this->config->servers->value) as $address) {
				$e = explode(':', $address, 2);
				$port = isset($e[1]) ? $e[1] : $this->config->port->value;

				$this->client->addServer($e[0], $port);
				$this->worker->addServer($e[0], $port);
			}

			$this->interval = $this->pushRequest(new GearmanNodeInterval($this, $this));
		}
	}
}

class GearmanNodeInterval extends Request {

	/**
	 * @method run
	 * @description Called when request iterated.
	 * @return integer Status.
	 */
	public function run() {
		$worker = $this->appInstance->worker;

		start:

		@$worker->work();
		$ret = $worker->returnCode();

		if ($ret == GEARMAN_IO_WAIT) {}

		if ($ret == GEARMAN_NO_JOBS) {
			$this->sleep(0.2);
		}

		if ($ret == GEARMAN_SUCCESS) {
			goto start;
		}

		@$worker->wait();
		$this->sleep(0.2);
	}
}
