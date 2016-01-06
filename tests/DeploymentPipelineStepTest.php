<?php

class DeploymentPipelineStepTest extends PipelineTest {

	protected static $fixture_file = 'PipelineTest.yml';

	/**
	 * Makes the dummy deployment step
	 *
	 * @return DeploymentPipelineStep
	 */
	public function getDummyDeployment($newEnv = false, $skipSnapshot = false) {
		$deployStep = $this->objFromFixture('DeploymentPipelineStep', 'testdeploy');
		$deployStep->Config = serialize(array('MaxDuration' => '3600'));
		$deployStep->write();
		$pipeline = $deployStep->Pipeline();

		// simulates a new environment that hasn't got any current build
		if($newEnv) {
			$pipeline->EnvironmentID = $this->idFromFixture('PipelineTest_Environment', 'newenvironment');
		}

		$pipeline->SkipSnapshot = $skipSnapshot;
		$pipeline->Config = serialize(array());
		$pipeline->write();
		return $deployStep;
	}

	public function testSuccessful() {
		$step = $this->getDummyDeployment();
		$step->start();

		// Assert not error at startup
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Snapshot', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot creating snapshot of database'));

		// Mark the snapshot as completed and check result
		$snapshot = $step->Pipeline()->PreviousSnapshot();
		$snapshot->markFinished();

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();

		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Deployment', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Deployment starting deployment'));

		// Mark the service as completed and check result
		$author = $this->objFromFixture('Member', 'author');
		$deployment = $step->Pipeline()->CurrentDeployment();
		$this->assertEquals($deployment->DeployerID, $author->ID);
		$deployment->markFinished();

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Finished', $step->Status);
		$this->assertTrue(PipelineTest_MockLog::has_message('Checking status of TestDeployStep:Deployment...'));
		$this->assertTrue(PipelineTest_MockLog::has_message('Step finished successfully!'));
	}

	/**
	 * Test failure at the snapshot step
	 */
	public function testSnapshotFailure() {
		$step = $this->getDummyDeployment();
		$step->start();

		// Assert not error at startup
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Snapshot', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot creating snapshot of database'));

		// Mark the service as completed and check result
		$snapshot = $step->Pipeline()->PreviousSnapshot();
		$snapshot->markFailed();

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Failed', $step->Status);
		$this->assertTrue(PipelineTest_MockLog::has_message('Checking status of TestDeployStep:Snapshot...'));
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot failed with task status Failed'));
	}

	/**
	 * Test snapshot is skipped when environment has no build
	 */
	public function testSnapshotSkipped() {
		$step = $this->getDummyDeployment(true);
		$step->start();

		// Assert not error at startup
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Snapshot', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('[Skipped] No current build, skipping snapshot'));

		// Mark the service as completed and check result
		$snapshot = $step->Pipeline()->PreviousSnapshot();
		$this->assertFalse($snapshot->exists(), 'No snapshot was created');
	}

	/**
	 * Test snapshot is skipped upon user request.
	 */
	public function testSnapshotSkippedByUser() {
		$step = $this->getDummyDeployment(false, true);
		$step->start();

		// Assert not error at startup
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Deployment', $step->Doing);
		$this->assertFalse(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot creating snapshot of database'));
		$snapshot = $step->Pipeline()->PreviousSnapshot();
		$this->assertFalse($snapshot->exists(), 'No snapshot was created');
	}

	/**
	 * Test failure at the deployment step
	 */
	public function testDeploymentFailure() {
		$step = $this->getDummyDeployment();
		$step->start();

		// Assert not error at startup
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Snapshot', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot creating snapshot of database'));

		// Mark the snapshot as completed and check result
		$snapshot = $step->Pipeline()->PreviousSnapshot();
		$snapshot->markFinished();

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();

		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Deployment', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Deployment starting deployment'));

		// Mark the service as completed and check result
		$deployment = $step->Pipeline()->CurrentDeployment();
		$deployment->markFailed();

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Failed', $step->Status);
		$this->assertTrue(PipelineTest_MockLog::has_message('Checking status of TestDeployStep:Deployment...'));
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Deployment failed with task status Failed'));
	}

	public function testDelayedSuccess() {
		$step = $this->getDummyDeployment();
		$step->start();

		// Assert not error at startup
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Snapshot', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot creating snapshot of database'));

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Snapshot', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('Checking status of TestDeployStep:Snapshot...'));
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot is still in progress'));

		// Mark the snapshot as completed and check result
		$snapshot = $step->Pipeline()->PreviousSnapshot();
		$snapshot->markFinished();

		// Advance to deployment
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Deployment', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Deployment starting deployment'));

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Started', $step->Status);
		$this->assertEquals('Deployment', $step->Doing);
		$this->assertTrue(PipelineTest_MockLog::has_message('Checking status of TestDeployStep:Deployment...'));
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Deployment is still in progress'));

		// Mark the service as completed and check result
		$deployment = $step->Pipeline()->CurrentDeployment();
		$deployment->markFinished();

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Finished', $step->Status);
		$this->assertTrue(PipelineTest_MockLog::has_message('Checking status of TestDeployStep:Deployment...'));
		$this->assertTrue(PipelineTest_MockLog::has_message('Step finished successfully!'));
	}

	public function testTimeout() {
		$step = $this->getDummyDeployment();
		$step->start();

		// Assert not error at startup
		$this->assertEquals('Started', $step->Status);
		$this->assertTrue(PipelineTest_MockLog::has_message('TestDeployStep:Snapshot creating snapshot of database'));

		// Go to two hours into the future
		SS_Datetime::set_mock_now(date('Y-m-d H:i:s', strtotime('+2 hours')));

		// Retry step
		PipelineTest_MockLog::clear();
		$step->start();
		$this->assertEquals('Failed', $step->Status);
		$this->assertTrue($step->isTimedOut());
		$this->assertTrue(PipelineTest_MockLog::has_message('Checking status of TestDeployStep:Snapshot...'));
		$this->assertTrue(PipelineTest_MockLog::has_message(
			'TestDeployStep:Snapshot took longer than 3600 seconds to run and has timed out'
		));
	}
}
