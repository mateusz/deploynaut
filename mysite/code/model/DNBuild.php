<?php

class DNBuild extends ViewableData {
	
	/**
	 *
	 * @var string
	 */
	protected $filename;
	
	/**
	 *
	 * @var string
	 */
	protected $buildname;
	
	/**
	 *
	 * @var string
	 */
	protected $simplename;
	
	/**
	 *
	 * @var DNData 
	 */
	protected $data;
	
	function __construct($filename, DNData $data) {
		$this->data = $data;
		
		$this->filename = $filename;
		$this->buildname = preg_replace('/\.tar\.gz$/', '', basename($this->filename));
		$this->simplename = preg_replace('/^[^-]+-/', '', $this->buildname);
		
		parent::__construct();
	}

	/**
	 *
	 * @return string
	 */
	public function Link() {
		return "naut/build/" . $this->name;
	}
	
	/**
	 *
	 * @return string
	 */
	public function FullName() {
		return $this->buildname;
	}
	
	/**
	 *
	 * @return string
	 */
	public function Name() {
		return $this->simplename;
	}
	
	/**
	 *
	 * @return string
	 */
	public function Filename() {
		return $this->filename;
	}
	
	/**
	 *
	 * @return \SS_Datetime 
	 */
	public function Created() {
		$d = new SS_Datetime();
		$d->setValue(date('Y-m-d H:i:s', filemtime($this->filename)));
		return $d;
	}
	
	/**
	 *
	 * @return \ArrayList 
	 */
	public function CurrentlyDeployedTo() {
		$output = new ArrayList;
		foreach($this->data->DNEnvironmentList() as $environment) {
			if($environment->CurrentBuild() == $this->buildname) $output->push($environment);
		}
		return $output;
	}

	/**
	 *
	 * @param type $environmentName 
	 */
	public function EverDeployedTo($environmentName) {
		$environment = $this->data->DNEnvironmentList()->byName($environmentName);
		
	}
}