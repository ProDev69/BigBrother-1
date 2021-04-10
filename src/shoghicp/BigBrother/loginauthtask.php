<?php
declare(strict_types=1);

namespace shoghicp\BigBrother;

use pocketmine\network\mcpe\protocol\types\GeneratorType;
use pocketmine\network\mcpe\VerifyLoginTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\inventory\CraftingGrid;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo as Info;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\RequestChunkRadiusPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\SourceInterface;
use pocketmine\scheduler\AsyncTask;
use pocketmine\level\Level;
use pocketmine\level\format\Chunk;
use pocketmine\timings\Timings;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use shoghicp\BigBrother\network\Packet;
use shoghicp\BigBrother\network\protocol\Login\EncryptionRequestPacket;
use shoghicp\BigBrother\network\protocol\Login\EncryptionResponsePacket;
use shoghicp\BigBrother\network\protocol\Login\LoginSuccessPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\AdvancementsPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\KeepAlivePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\PlayerPositionAndLookPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\TitlePacket;
use shoghicp\BigBrother\network\protocol\Play\Server\SelectAdvancementTabPacket;
use shoghicp\BigBrother\network\protocol\Play\Server\UnloadChunkPacket;
use shoghicp\BigBrother\network\ProtocolInterface;
use shoghicp\BigBrother\entity\ItemFrameBlockEntity;
use shoghicp\BigBrother\utils\Binary;
use shoghicp\BigBrother\utils\InventoryUtils;
use shoghicp\BigBrother\utils\RecipeUtils;
use shoghicp\BigBrother\utils\SkinImage;

    class loginauthtask extends AsyncTask{

				/** @var string */
				private $username;
				/** @var string */
				private $hash;

				/**
				 * @param DesktopPlayer $player
				 * @param string $username
				 * @param string $hash
				 */
				public function __construct(DesktopPlayer $player, string $username, string $hash){
					self::storeLocal($player);
					$this->username = $username;
					$this->hash = $hash;
				}

				/**
				 * @override
				 */
				public function onRun(){
					$result = null;

					$query = http_build_query([
						"username" => $this->username,
						"serverId" => $this->hash
					]);

					$response = Internet::getURL("https://sessionserver.mojang.com/session/minecraft/hasJoined?".$query, 5, [], $err, $header, $status);
					if($response === false || $status !== 200){
						$this->publishProgress("InternetException: failed to fetch session data for '$this->username'; status=$status; err=$err; response_header=".json_encode($header));
						$this->setResult(false);
						return;
					}

					$this->setResult(json_decode($response, true));
				}

				/**
				 * @override
				 * @param Server $server
				 * @param mixed $progress
				 */
				public function onProgressUpdate(Server $server, $progress){
					$server->getLogger()->error($progress);
				}

				/**
				 * @override
				 * @param $server
				 */
				public function onCompletion(Server $server){
					$result = $this->getResult();
					/** @var DesktopPlayer $player */
					$player = self::fetchLocal();
					if(is_array($result) and isset($result["id"])){
						$player->bigBrother_authenticate($result["id"], $result["properties"]);
					}else{
						$player->close("", "User not premium");
					}
				}
    }
