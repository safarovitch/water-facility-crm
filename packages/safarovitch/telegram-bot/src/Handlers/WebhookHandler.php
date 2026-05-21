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
        // Deep-link login flow: when the website generated a login token and
        // the user opened t.me/<bot>?start=login_<token>, Telegram delivers
        // "/start login_<token>" as the message text. Parse it here and hand
        // off to the OTP service so the bot DMs the verification code.
        $text = (string) ($this->message?->text() ?? '');
        if (preg_match('/^\/start\s+login_([A-Za-z0-9]+)/', $text, $m)) {
            $this->handleLoginDeepLink($m[1]);
            return;
        }

        if (!$this->chat->user_id) {
            $this->promptRegistration();
            return;
        }
        $this->mainMenu();
    }

    protected function handleLoginDeepLink(string $token): void
    {
        $service = app(\App\Services\TelegramOtpService::class);
        $phone = $service->resolveLoginToken($token);

        if (!$phone) {
            $this->chat->message("Срок действия ссылки истёк. Запросите код входа заново на сайте.")->send();
            return;
        }

        // If the chat is already bound to a user whose phone matches, we can
        // send the OTP right away. Otherwise we need a contact share to map
        // chat_id ↔ phone for the first time.
        $userPhone = \App\Models\UserPhone::where('phone', $phone)->first();
        $userIsBoundHere = $userPhone && $this->chat->user_id && (int) $userPhone->user_id === (int) $this->chat->user_id;

        if ($userIsBoundHere || $userPhone) {
            $service->dispatchOtp($this->chat, $phone);
            return;
        }

        // Stash the pending phone so once the user shares their contact we
        // can confirm the contact matches and then send the OTP.
        $this->chat->setStateData(['pending_login_phone' => $phone]);
        $this->chat->forceFill(['current_state' => 'login_awaiting_contact'])->save();

        $this->chat->message("Для входа на сайт подтвердите номер, поделившись контактом ниже.")
            ->replyKeyboard(
                ReplyKeyboard::make()->buttons([
                    ReplyButton::make('📞 Поделиться контактом')->requestContact(),
                ])->resize()
            )->send();
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

        // If we landed here via a login deep-link, the cached phone must match
        // the contact the user just shared — otherwise someone is trying to
        // log in as a different number.
        $stateData = $this->chat->state_data ?? [];
        $pendingPhone = $stateData['pending_login_phone'] ?? null;

        if ($pendingPhone && $pendingPhone !== $phone) {
            $this->chat->message("Этот номер не совпадает с тем, что вы ввели на сайте. Попробуйте ещё раз.")
                ->removeReplyKeyboard()->send();
            $this->chat->clearState();
            return;
        }

        $userPhone = \App\Models\UserPhone::where('phone', $phone)->first();

        if ($userPhone) {
            $user = $userPhone->user;
        } else {
            $user = User::create([
                'name' => $contact->firstName() ?? 'Telegram Client',
                'email' => null,
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

        // Web-login path: send the OTP and stop — don't open the order menu.
        if ($pendingPhone) {
            $this->chat->clearState();
            app(\App\Services\TelegramOtpService::class)->dispatchOtp($this->chat, $phone);
            $this->chat->message("Код отправлен. Вернитесь на сайт и введите его.")
                ->removeReplyKeyboard()->send();
            return;
        }

        $this->chat->message("Отлично! Ваш аккаунт привязан.")
            ->removeReplyKeyboard()->send();

        $this->mainMenu();
    }

    public function mainMenu(): void
    {
        $this->chat->clearState();
        if (isset($this->callbackQueryId)) {
            try { $this->deleteKeyboard(); } catch (\Exception $e) {}
        }
        $this->chat->message("Что желаете? 💧")
            ->keyboard(MainMenuKeyboard::make())->send();
    }

    // --- Action Router Layer ---
    public function actionNewOrder(): void
    {
        if (isset($this->callbackQueryId)) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->handleNewOrder();
    }

    public function actionSelectProduct(): void
    {
        if (isset($this->callbackQueryId)) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
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
        if (isset($this->callbackQueryId)) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->confirmOrder($stateData);
        $this->mainMenu();
    }
    
    public function actionCancelOrder(): void
    {
        if (isset($this->callbackQueryId)) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->cancelOrder();
        $this->mainMenu();
    }

    public function actionRepeatOrder(): void
    {
        if (isset($this->callbackQueryId)) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new OrderHandler($this->chat, $this->bot))->handleRepeatOrder();
    }

    public function actionMyOrders(): void
    {
        if (isset($this->callbackQueryId)) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        (new TrackingHandler($this->chat, $this->bot))->handleListOrders();
        $this->mainMenu();
    }

    public function actionProfile(): void
    {
        if (isset($this->callbackQueryId)) { try { $this->deleteKeyboard(); } catch (\Exception $e) {} }
        $user = $this->chat->user;
        $this->chat->message("👤 Профиль:\nИмя: {$user->name}\nТелефон: {$user->phone}")->send();
        $this->mainMenu();
    }
}
