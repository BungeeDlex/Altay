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

namespace pocketmine\item;

class Elytra extends Item{
	public function __construct(int $meta = 0){
		parent::__construct(Item::ELYTRA, $meta, "Elytra Wings");
	}

	public function getArmorSlot() : int{
		return Armor::SLOT_CHESTPLATE;
	}

	public function getMaxStackSize() : int{
		return 1;
	}
}
