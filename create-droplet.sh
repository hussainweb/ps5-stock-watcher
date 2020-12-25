#!/usr/bin/env bash

read -p "Are you sure you want to create a new droplet? " -n 1 -r
if [[ $REPLY =~ ^[Yy]$ ]]
then
    doctl compute droplet create --image ubuntu-20-04-x64 --size s-1vcpu-1gb --region tor1 ps5-stock-checker --ssh-keys 12780626,29268064 --context personal
    echo "Waiting to create..."
    sleep 5
    doctl compute droplet list --context personal
fi
