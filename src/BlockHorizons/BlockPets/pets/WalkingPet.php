<?php

declare(strict_types = 1);

namespace BlockHorizons\BlockPets\pets;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;

abstract class WalkingPet extends IrasciblePet {

	/** @var int */
	protected $jumpTicks = 0;

	public function onUpdate(int $currentTick): bool {
		if(!$this->checkUpdateRequirements()) {
			return true;
		}
		if($this->isRiding()) {
			$this->yaw = $this->getPetOwner()->yaw;
			$this->pitch = $this->getPetOwner()->pitch;
			$this->updateMovement();
			return parent::onUpdate($currentTick);
		}
		if(!parent::onUpdate($currentTick)) {
			return false;
		}
		if(!$this->isAlive()) {
			return false;
		}
		$petOwner = $this->getPetOwner();
		if($this->isAngry()) {
			return $this->doAttackingMovement();
		}

		if($this->jumpTicks > 0) {
			$this->jumpTicks--;
		}

		if(!$this->isOnGround()) {
			if($this->motionY > -$this->gravity * 4) {
				$this->motionY = -$this->gravity * 4;
			} else {
				$this->motionY += $this->isInsideOfWater() ? $this->gravity : -$this->gravity;
			}
		} else {
			$this->motionY -= $this->gravity;
		}
		if($this->isCollidedHorizontally && $this->jumpTicks === 0) {
			$this->jump();
		}
		$this->move($this->motionX, $this->motionY, $this->motionZ);

		$x = $petOwner->x + $this->xOffset - $this->x;
		$y = $petOwner->y - $this->y;
		$z = $petOwner->z + $this->zOffset - $this->z;

		if($x * $x + $z * $z < 4 + $this->getScale()) {
			$this->motionX = 0;
			$this->motionZ = 0;
		} else {
			$this->motionX = $this->getSpeed() * 0.15 * ($x / (abs($x) + abs($z)));
			$this->motionZ = $this->getSpeed() * 0.15 * ($z / (abs($x) + abs($z)));
		}
		$this->yaw = rad2deg(atan2(-$x, $z));
		$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));

		$this->move($this->motionX, $this->motionY, $this->motionZ);
		$this->updateMovement();
		return $this->isAlive();
	}

	public function doAttackingMovement(): bool {
		$target = $this->getTarget();
		if(!$this->checkAttackRequirements()) {
			return false;
		}

		if($this->jumpTicks > 0) {
			$this->jumpTicks--;
		}

		if(!$this->isOnGround()) {
			if($this->motionY > -$this->gravity * 4) {
				$this->motionY = -$this->gravity * 4;
			} else {
				$this->motionY += $this->isInsideOfWater() ? $this->gravity : -$this->gravity;
			}
		} else {
			$this->motionY -= $this->gravity;
		}
		if($this->isCollidedHorizontally && $this->jumpTicks === 0) {
			$this->jump();
		}
		$this->move($this->motionX, $this->motionY, $this->motionZ);

		$x = $target->x - $this->x;
		$y = $target->y - $this->y;
		$z = $target->z - $this->z;

		if($x * $x + $z * $z < 1.2) {
			$this->motionX = 0;
			$this->motionZ = 0;
		} else {
			$this->motionX = $this->getSpeed() * 0.15 * ($x / (abs($x) + abs($z)));
			$this->motionZ = $this->getSpeed() * 0.15 * ($z / (abs($x) + abs($z)));
		}
		$this->yaw = rad2deg(atan2(-$x, $z));
		$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));

		$this->move($this->motionX, $this->motionY, $this->motionZ);

		if($this->distance($target) <= $this->scale + 0.5 && $this->waitingTime <= 0) {
			$event = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getAttackDamage());
			if($target->getHealth() - $event->getFinalDamage() <= 0) {
				if($target instanceof Player) {
					$this->addPetLevelPoints($this->getLoader()->getBlockPetsConfig()->getPlayerExperiencePoints());
				} else {
					$this->addPetLevelPoints($this->getLoader()->getBlockPetsConfig()->getEntityExperiencePoints());
				}
				$this->calmDown();
			}

			$target->attack($event);

			$this->waitingTime = 12;
		} elseif($this->distance($this->getPetOwner()) > 25 || $this->distance($this->getTarget()) > 15) {
			$this->calmDown();
		}
		$this->updateMovement();
		$this->waitingTime--;
		return $this->isAlive();
	}

	public function jump() {
		$this->motionY = $this->gravity * 8;
		$this->move($this->motionX, $this->motionY, $this->motionZ);
		$this->jumpTicks = 5;
	}

	public function doRidingMovement(float $motionX, float $motionZ): bool {
		$rider = $this->getPetOwner();

		$this->pitch = $rider->pitch;
		$this->yaw = $rider->yaw;

		$x = $this->getDirectionVector()->x / 2.5 * $this->getSpeed();
		$z = $this->getDirectionVector()->z / 2.5 * $this->getSpeed();

		if($this->jumpTicks > 0) {
			$this->jumpTicks--;
		}

		if(!$this->isOnGround()) {
			if($this->motionY > -$this->gravity * 4) {
				$this->motionY = -$this->gravity * 4;
			} else {
				$this->motionY -= $this->gravity;
			}
		} else {
			$this->motionY -= $this->gravity;
		}
		if($this->isCollidedHorizontally && $this->jumpTicks === 0) {
			$this->jump();
		}

		$finalMotion = [0, 0];
		switch($motionZ) {
			case 1:
				$finalMotion = [$x, $z];
				break;
			case 0:
				break;
			case -1:
				$finalMotion = [-$x, -$z];
				break;
			default:
				$average = $x + $z / 2;
				$finalMotion = [$average / 1.414 * $motionZ, $average / 1.414 * $motionX];
				break;
		}
		switch($motionX) {
			case 1:
				$finalMotion = [$z, -$x];
				break;
			case 0:
				break;
			case -1:
				$finalMotion = [-$z, $x];
				break;
		}

		$this->move($finalMotion[0], $this->motionY, $finalMotion[1]);
		$this->updateMovement();
		return $this->isAlive();
	}

	/**
	 * @param array $properties
	 */
	public function useProperties(array $properties) {
		parent::useProperties($properties);
	}
}
