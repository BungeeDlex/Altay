<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\entity;


use pocketmine\block\Liquid;
use pocketmine\entity\behavior\BehaviorPool;
use pocketmine\entity\helper\EntityJumpHelper;
use pocketmine\entity\helper\EntityMoveHelper;
use pocketmine\entity\pathfinder\EntityNavigator;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\timings\Timings;

abstract class Mob extends Living{

	/** @var BehaviorPool */
	protected $behaviorPool;
	/** @var BehaviorPool */
	protected $targetBehaviorPool;
	/** @var EntityNavigator */
	protected $navigator;
	/** @var Vector3 */
	protected $lookPosition;
	/** @var Entity[] */
	protected $seenEntities = [];
	/** @var Entity[] */
	protected $unseenEntities = [];
	protected $jumpCooldown = 0;
	/** @var Vector3 */
	protected $homePosition;
	/** @var int */
	protected $livingSoundTime = 0;

	protected $moveForward = 0.0;
	protected $moveStrafing = 0.0;

	protected $landMovementFactor = 0.0;
	protected $jumpMovementFactor = 0.02;

	protected $isJumping = false;
	protected $jumpTicks = 0;

	/** @var EntityMoveHelper */
	protected $moveHelper;
	/** @var EntityJumpHelper */
	protected $jumpHelper;

	/**
	 * @return Vector3
	 */
	public function getHomePosition() : Vector3{
		return $this->homePosition;
	}

	/**
	 * Get number of ticks, at least during which the living entity will be silent.
	 *
	 * @return int
	 */
	public function getTalkInterval() : int{
		return 80;
	}

	/**
	 * @param Vector3 $homePosition
	 */
	public function setHomePosition(Vector3 $homePosition) : void{
		$this->homePosition = $homePosition;
	}

	public function getAIMoveSpeed() : float{
		return $this->landMovementFactor;
	}

	public function setAIMoveSpeed(float $value) : void{
		$this->landMovementFactor = $value;
	}

	/**
	 * @return float
	 */
	public function getMoveForward() : float{
		return $this->moveForward;
	}

	/**
	 * @param float $moveForward
	 */
	public function setMoveForward(float $moveForward) : void{
		$this->moveForward = $moveForward;
	}

	/**
	 * @return float
	 */
	public function getMoveStrafing() : float{
		return $this->moveStrafing;
	}

	/**
	 * @param float $moveStrafing
	 */
	public function setMoveStrafing(float $moveStrafing) : void{
		$this->moveStrafing = $moveStrafing;
	}

	/**
	 * @return bool
	 */
	public function isJumping() : bool{
		return $this->isJumping;
	}

	/**
	 * @param bool $isJumping
	 */
	public function setJumping(bool $isJumping) : void{
		$this->isJumping = $isJumping;
	}

	/**
	 * @return EntityMoveHelper
	 */
	public function getMoveHelper() : EntityMoveHelper{
		return $this->moveHelper;
	}

	/**
	 * @return EntityJumpHelper
	 */
	public function getJumpHelper() : EntityJumpHelper{
		return $this->jumpHelper;
	}

	/**
	 * @param CompoundTag $nbt
	 */
	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->targetBehaviorPool = new BehaviorPool();
		$this->behaviorPool = new BehaviorPool();
		$this->navigator = new EntityNavigator($this);
		$this->moveHelper = new EntityMoveHelper($this);
		$this->jumpHelper = new EntityJumpHelper($this);

		$this->addBehaviors();
		$this->setImmobile(boolval($nbt->getByte("NoAI", 1)));

		$this->stepHeight = 0.6;
	}

	/**
	 * @return CompoundTag
	 */
	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setByte("NoAI", intval($this->isImmobile()));

		return $nbt;
	}

	/**
	 * @param int $diff
	 *
	 * @return bool
	 */
	public function entityBaseTick(int $diff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($diff);

		if(!$this->isImmobile()){
			if($this->jumpTicks > 0){
				$this->jumpTicks--;
			}

			$this->onBehaviorUpdate();

			if($this->isAlive() and $this->random->nextBoundedInt(1000) < $this->livingSoundTime++){
				$this->livingSoundTime -= $this->getTalkInterval();
				$this->playLivingSound();
			}
		}

		return $hasUpdate;
	}

	/**
	 * @return null|string
	 */
	public function getLivingSound() : ?string{
		return null;
	}

	public function playLivingSound() : void{
		$sound = $this->getLivingSound();

		if($sound !== null and $this->chunk !== null){
			$pk = new PlaySoundPacket();
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->pitch = $this->isBaby() ? 2 : 1;
			$pk->volume = 1.0;
			$pk->soundName = $sound;

			$this->level->addChunkPacket($this->chunk->getX(), $this->chunk->getZ(), $pk);
		}
	}

	protected function onBehaviorUpdate() : void{
		Timings::$mobBehaviorUpdateTimer->startTiming();
		$this->targetBehaviorPool->onUpdate();
		$this->behaviorPool->onUpdate();
		Timings::$mobBehaviorUpdateTimer->stopTiming();

		Timings::$mobNavigationUpdateTimer->startTiming();
		$this->navigator->onNavigateUpdate();
		Timings::$mobNavigationUpdateTimer->stopTiming();

		if($this->isJumping){
			if($this->isInsideOfWater()){
				$this->handleWaterMovement();
			}elseif($this->isInsideOfLava()){
				$this->handleWaterMovement(); // same
			}elseif($this->onGround and $this->jumpTicks === 0){
				$this->jump();
				$this->jumpTicks = 10;
			}
		}else{
			$this->jumpTicks = 0;
		}

		$this->moveStrafing *= 0.98;
		$this->moveForward *= 0.98;
		$this->moveWithHeading($this->moveStrafing, $this->moveForward);

		$this->moveHelper->onUpdate();
		$this->clearSightCache();
		if($this->getLookPosition() !== null){
			$this->lookAt($this->getLookPosition(), true);
			$this->lookPosition = null;
		}
		$this->jumpHelper->doJump();
	}

	/**
	 * @param Entity $target
	 *
	 * @return bool
	 */
	public function canSeeEntity(Entity $target) : bool{
		if(in_array($target->getId(), $this->unseenEntities)){
			return false;
		}elseif(in_array($target->getId(), $this->seenEntities)){
			return true;
		}else{
			// TODO: Fix seen from corners
			$canSee = $this->getNavigator()->isClearBetweenPoints($this, $target);

			if($canSee){
				$this->seenEntities[] = $target->getId();
			}else{
				$this->unseenEntities[] = $target->getId();
			}

			return $canSee;
		}
	}

	public function clearSightCache() : void{
		$this->seenEntities = [];
		$this->unseenEntities = [];
	}

	/**
	 * @return null|Vector3
	 */
	public function getLookPosition() : ?Vector3{
		return $this->lookPosition;
	}

	/**
	 * @param null|Vector3 $pos
	 */
	public function setLookPosition(?Vector3 $pos) : void{
		$this->lookPosition = $pos;
	}

	protected function addBehaviors() : void{

	}

	/**
	 * @return BehaviorPool
	 */
	public function getBehaviorPool() : BehaviorPool{
		return $this->behaviorPool;
	}

	/**
	 * @return BehaviorPool
	 */
	public function getTargetBehaviorPool() : BehaviorPool{
		return $this->targetBehaviorPool;
	}

	public function moveForward(float $spm) : bool{
		return $this->moveTo($this->getDirectionVector(), $spm);
	}

	public function moveBack(float $spm) : bool{
		return $this->moveTo($this->getDirectionVector()->multiply(-1), $spm);
	}

	/**
	 * @param Vector3 $dir
	 * @param float   $spm
	 *
	 * @return bool
	 */
	public function moveTo(Vector3 $dir, float $spm) : bool{
		if($this->jumpCooldown > 0) $this->jumpCooldown--;

		$sf = $this->getMovementSpeed() * $spm * 0.7 * $this->getAiMoveSpeed();
		$dir->y = 0;

		$coord = $this->add($dir->multiply($sf)->add($dir->multiply($this->width * 0.5)));

		$block = $this->level->getBlock($coord);

		if($this->isInsideOfSolid()){
			$block = $this->level->getBlock($this);
		}

		$blockUp = $block->getSide(Facing::UP);
		$blockUpUp = $block->getSide(Facing::UP, 2);

		$bb = $block->getBoundingBox();

		$collide = $block->isSolid() or ($this->height >= 1 and $blockUp->isSolid());

		if($collide){
			//if($bb !== null and $bb->maxY <= $this->y){
			$collide = false;
			$this->stepHeight = 1.0;
			//}
		}

		if(!$collide){
			if(!$this->onGround and $this->jumpCooldown === 0 and !$this->isSwimmer()) return true;

			$velocity = $dir->multiply($sf);
			$entityVelocity = $this->getMotion();
			$entityVelocity->y = 0;

			$this->motion = $this->getMotion()->add($velocity->subtract($entityVelocity));
			return true;
		}else{
			if($this->canClimb()){
				$this->motion->y = 0.2;
				$this->jumpCooldown = 20;
				return true;
			}elseif((!$blockUp->isSolid() and !($this->height > 1 and $blockUpUp->isSolid()) or $block->isPassable($this)) or $this->isSwimmer()){
				$this->jumpCooldown = 20;
				return true;
			}else{
				$this->motion->x = $this->motion->z = 0;
			}
		}
		return false;
	}

	/**
	 * @return EntityNavigator
	 */
	public function getNavigator() : EntityNavigator{
		return $this->navigator;
	}

	/**
	 * @return bool
	 */
	public function canBePushed() : bool{
		return !$this->isImmobile();
	}

	public function updateLeashedState() : void{
		parent::updateLeashedState();

		if($this->isLeashed() and $this->leashedToEntity !== null){
			$entity = $this->leashedToEntity;
			$f = $this->distance($entity);

			if($this instanceof Tamable and $this->isSitting()){
				if($f > 10){
					$this->clearLeashed(true, true);
				}
				return;
			}

			if($f > 4){
				$this->navigator->tryMoveTo($entity, 1.0);
			}

			if($f > 6){
				$d0 = ($entity->x - $this->x) / $f;
				$d1 = ($entity->y - $this->y) / $f;
				$d2 = ($entity->z - $this->z) / $f;

				$this->motion->x += $d0 * abs($d0) * 0.4;
				$this->motion->y += $d1 * abs($d1) * 0.4;
				$this->motion->z += $d2 * abs($d2) * 0.4;
			}

			if($f > 10){
				$this->clearLeashed(true, true);
			}
		}
	}

	/**
	 * @return bool
	 */
	public function canDespawn() : bool{
		return !$this->isImmobile() and !$this->isLeashed() and $this->getOwningEntityId() === null;
	}

	/**
	 * @param Vector3 $pos
	 *
	 * @return float
	 */
	public function getBlockPathWeight(Vector3 $pos) : float{
		return 0.0;
	}

	/**
	 * @return bool
	 */
	public function canSpawnHere() : bool{
		return parent::canSpawnHere() and $this->getBlockPathWeight($this) > 0;
	}

	public function moveWithHeading(float $strafe, float $forward){
		if(!$this->isInsideOfWater()){
			if(!$this->isInsideOfLava()){
				$f4 = 0.91;

				if($this->onGround){
					$f4 = $this->level->getBlock($this->down())->getFrictionFactor() * 0.91;
				}

				$f = 0.16277136 / ($f4 * $f4 * $f4);

				if($this->onGround){
					$f5 = $this->getAIMoveSpeed() * $f;
				}else{
					$f5 = $this->jumpMovementFactor;
				}

				$this->moveFlying($strafe, $forward, $f5);
			}else{
				$d1 = $this->y;
				$this->moveFlying($strafe, $forward, 0.02);
				$this->move($this->motion->x, $this->motion->y, $this->motion->z);
				$this->motion->x *= 0.5;
				$this->motion->y *= 0.5;
				$this->motion->z *= 0.5;
				$this->motion->y -= 0.02;

				if($this->isCollidedHorizontally and $this->level->getBlock($this->add($this->motion->x, $this->motion->y + 0.6000000238418579 - $this->y + $d1, $this->motion->z)) instanceof Liquid){
					$this->motion->y = 0.30000001192092896;
				}
			}
		}else{
			$d0 = $this->y;
			$f1 = 0.8;
			$f2 = 0.02;
			$f3 = 0; // TODO: check enchantment

			if($f3 > 3.0){
				$f3 = 3.0;
			}

			if(!$this->onGround){
				$f3 *= 0.5;
			}

			if($f3 > 0.0){
				$f1 += (0.54600006 - $f1) * $f3 / 3.0;
				$f2 += ($this->getAIMoveSpeed() * 1.0 - $f2) * $f3 / 3.0;
			}

			$this->moveFlying($strafe, $forward, $f2);
			$this->move($this->motion->x, $this->motion->y, $this->motion->z);
			$this->motion->x *= $f1;
			$this->motion->y *= 0.800000011920929;
			$this->motion->z *= $f1;
			$this->motion->y -= 0.02;

			if($this->isCollidedHorizontally and $this->level->getBlock($this->add($this->motion->x, $this->motion->y + 0.6000000238418579 - $this->y + $d0, $this->motion->z)) instanceof Liquid){
				$this->motion->y = 0.30000001192092896;
			}
		}
	}
}