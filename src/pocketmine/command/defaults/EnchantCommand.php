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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\lang\TranslationContainer;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;
use pocketmine\utils\TextFormat;
use function count;
use function is_numeric;

class EnchantCommand extends VanillaCommand{

	public function __construct(string $name){
		$enchantNames = [];
		for($i = 0; $i <= 32; $i++){
			$enchant = Enchantment::getEnchantment($i);
			if($enchant !== null){
				$enchantNames[] = str_replace(["%enchantment.", "."], ["", "_"], $enchant->getName());
			}
		}
		parent::__construct($name, "%pocketmine.command.enchant.description", "%commands.enchant.usage", [], [
			[
				new CommandParameter("player", AvailableCommandsPacket::ARG_TYPE_TARGET),
				new CommandParameter("enchantName", AvailableCommandsPacket::ARG_TYPE_STRING, false, new CommandEnum("enchantNames", $enchantNames)),
				new CommandParameter("level", AvailableCommandsPacket::ARG_TYPE_INT)
			], [
				new CommandParameter("player", AvailableCommandsPacket::ARG_TYPE_INT, false),
				new CommandParameter("enchantmentId", AvailableCommandsPacket::ARG_TYPE_INT, false),
				new CommandParameter("level", AvailableCommandsPacket::ARG_TYPE_INT, false)
			]
		]);
		$this->setPermission("pocketmine.command.enchant");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		$player = $sender->getServer()->getPlayer($args[0]);

		if($player === null){
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.player.notFound"));
			return true;
		}

		$item = $player->getInventory()->getItemInHand();

		if($item->isNull()){
			$sender->sendMessage(new TranslationContainer("commands.enchant.noItem"));
			return true;
		}

		if(is_numeric($args[1])){
			$enchantment = Enchantment::getEnchantment((int) $args[1]);
		}else{
			$enchantment = Enchantment::getEnchantmentByName($args[1]);
		}

		if(!($enchantment instanceof Enchantment)){
			$sender->sendMessage(new TranslationContainer("commands.enchant.notFound", [$args[1]]));
			return true;
		}

		$item->addEnchantment(new EnchantmentInstance($enchantment, min(30, (int) ($args[2] ?? 1))));
		$player->getInventory()->setItemInHand($item);

		self::broadcastCommandMessage($sender, new TranslationContainer("%commands.enchant.success", [$player->getName()]));
		return true;
	}
}
