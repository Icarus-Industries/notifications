<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015, 2016, 2017, 2018, 2019  Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Notifications\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Seat\Eveapi\Models\Sde\MapDenormalize;
use Symfony\Component\Yaml\Yaml;

class SovStructureReinforced extends AbstractNotification
{
    /**
     * @var \Seat\Eveapi\Models\Character\CharacterNotification
     */
    private $notification;

    /**
     * @var mixed
     */
    private $content;

    /**
     * SovStructureReinforced constructor.
     *
     * @param $notification
     */
    public function __construct($notification)
    {
        $this->notification = $notification;
        $this->content = Yaml::parse($this->notification->text);
    }

    /**
     * @param $notifiable
     * @return mixed
     */
    public function via($notifiable)
    {
        return $notifiable->notificationChannels();
    }

    /**
     * @param $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $system = MapDenormalize::find($this->content['solarSystemID']);

        return (new MailMessage)
            ->subject('Sovereignty Structure Reinforced Notification!')
            ->line(
                sprintf('A sovereignty structure has been reinforced (%s)!', $this->campaignEventType($this->content['campaignEventType'])))
            ->line(
                sprintf('Nodes will decloak at %s', $this->mssqlTimestampToDate($this->content['decloakTime'])->toRfc7231String())
            )
            ->action(
                sprintf('System : %s (%s)', $system->itemName, number_format($system->security, 2)),
                sprintf('https://zkillboard.com/%s/%d', 'system', $system->itemID));
    }

    /**
     * @param $notifiable
     * @return \Illuminate\Notifications\Messages\SlackMessage
     */
    public function toSlack($notifiable)
    {
        return (new SlackMessage)
            ->content('A sovereignty structure has been reinforced!')
            ->from('SeAT SovStructureReinforced')
            ->attachment(function ($attachment) {
                $attachment->field(function ($field) {
                        $system = MapDenormalize::find($this->content['solarSystemID']);

                        $field->title('System')
                            ->content(
                                $this->zKillBoardToSlackLink(
                                    'system',
                                    $system->itemID,
                                    sprintf('%s (%s)', $system->itemName, number_format($system->security, 2))
                                )
                            );
                    })
                    ->field(function ($field) {

                        $field->title('Structure')
                            ->content(
                                $this->campaignEventType($this->content['campaignEventType'])
                            );

                    })
                    ->field(function ($field) {

                        $field->title('Node decloak')
                            ->content(
                                $this->mssqlTimestampToDate($this->content['decloakTime'])->toRfc7231String()
                            );

                    });
            })->warning();
    }

    /**
     * @param $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $this->content;
    }
}
