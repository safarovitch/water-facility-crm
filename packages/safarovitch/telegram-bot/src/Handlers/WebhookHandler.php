<?php

namespace Safarovitch\TelegramBot\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler as DefStudioWebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Stringable;
use Illuminate\Support\Str;
use App\Models\User;
use Safarovitch\TelegramBot\Keyboards\MainMenuKeyboard;

class WebhookHandler extends DefStudioWebhookHandler
{
    public function handleChatMessage(Stringable $text): void
    {
        if ($this->message?->contact() !== null) {
            $this->handleContactSharing($this->message->contact());
            return;
        }

        if (!$this->chat->user_id) {
            $this->promptRegistration();
            return;
        }

        // FSM Router Delegator
        $state = $this->chat->current_state;
        
        if ($state && Str::startsWith($state, 'order_')) {
            $orderHandler = new OrderHandler($this->chat, $this->bot);
            
            if ($state === 'order_waiting_quantity') {
                $orderHandler->processQuantityInput($text, $this->chat->state_data ?? []);
                return;
            }

            if ($state === 'order_waiting_address') {
                $orderHandler->processAddressInput($text, $this->chat->state_data ?? []);
                return;
            }
        }

        if ($text->toString() === 'Главное меню') {
            $this->mainMenu();
            return;
        }

        $this->reply('Пожалуйста, воспользуйтесь меню:');
        $this->mainMenu();
    }

    public function start(): void
    {
        if (!$this->chat->user_id) {
            $this->promptRegistration();
            return;
        }
        $this->mainMenu();
    }

    protected function promptRegistration(): void
    {
        $this->chat->message("Салом! Добро пожаловать в Water Bot 🇹🇯\nДля начала работы, пожалуйста, поделитесь вашим номером телефона.")
            ->replyKeyboard(
                ReplyKeyboard::make()->buttons([
                    ReplyButton::make('📞 Поделиться контактом')->requestContact()
                ])->resize()
            )->send();
    }

    protected function handleContactSharing($contact): void
    {
        $phone = $contact->phoneNumber();
        if (!$phone) {
            $this->reply('Не удалось получить номер телефона. Попробуйте еще раз.');
            return;
        }

        if (!Str::startsWith($phone, '+')) {
            $phone = '+' . $phone;
        }

        $userPhone = \App\Models\UserPhone::where('phone', $phone)->first();
        
        if ($userPhone) {
            $user = $userPhone->user;
        } else {
            $user = User::create([
                'name' => $contact->firstName() ?? 'Telegram Client',
                'email' => "tg_{$this->chat->chat_id}@example.local",
                'password' => bcrypt(Str::random(16)),
            ]);
            $user->assignRole('Client');

            $user->phones()->create([
                'phone' => $phone,
                'label' => 'Telegram',
                'is_default' => true,
            ]);
        }

        $this->chat->user_id = $user->id;
        $this->chat->save();

        $this->chat->message("Отлично! Ваш аккаунт привязан.")
            ->replyKeyboard(ReplyKeyboard::make()->remove())->send();

        $this->mainMenu();
    }

    public function mainMenu(): void
    {
        $this->chat->clearState();
        if ($this->callbackQueryId) {
            try { $this->deleteKeyboard(); } catch (\Exception $e) {}
        }
        $this->chat->message("Что желаете? 💧")
            ->keyboard(MainMenuKeyboard::make())->send();
    }

    // --- Action Router Layer ---
    public function actionNewOrder(): void
    {
        if ($this->callbackQueryId) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->handleNewOrder();
    }

    public function actionSelectProduct(): void
    {
        if ($this->callbackQueryId) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->handleSelectProduct((int) $this->data->get('id'));
    }

    public function actionConfirmOrder(): void
    {
        $stateData = $this->chat->state_data;
        if ($this->chat->current_state !== 'order_confirming' || !$stateData) {
            $this->reply("Срок действия сессии истек.");
            $this->mainMenu();
            return;
        }
        if ($this->callbackQueryId) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->confirmOrder($stateData);
        $this->mainMenu();
    }
    
    public function actionCancelOrder(): void
    {
        if ($this->callbackQueryId) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->cancelOrder();
        $this->mainMenu();
    }

    public function actionRepeatOrder(): void
    {
        if ($this->callbackQueryId) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->handleRepeatOrder();
    }

    public function actionMyOrders(): void
    {
        if ($this->callbackQueryId) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new TrackingHandler($this->chat, $this->bot))->handleListOrders();
        $this->mainMenu();
    }

    public function actionProfile(): void
    {
        if ($this->callbackQueryId) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        $user = $this->chat->user;
        $this->chat->message("👤 Профиль:\nИмя: {$user->name}\nТелефон: {$user->phone}")->send();
        $this->mainMenu();
    }
}
