#!/bin/bash

# ==========================================
# 1. CORE: Enable Root Login First
# ==========================================
# Ensure HOME environment variable exists, critical for subsequent scripts
export HOME=/root

# Detect default user (admin for Debian/AmazonLinux, ubuntu for Ubuntu)
DEFAULT_USER="admin"
if [ -d "/home/ubuntu" ]; then DEFAULT_USER="ubuntu"; fi

# Setup root ssh directory
mkdir -p /root/.ssh
chmod 700 /root/.ssh

# Copy authorized_keys from default user to root
if [ -f /home/$DEFAULT_USER/.ssh/authorized_keys ]; then
    cp /home/$DEFAULT_USER/.ssh/authorized_keys /root/.ssh/
    chmod 600 /root/.ssh/authorized_keys
    chown root:root /root/.ssh/authorized_keys
fi

# Configure SSHD to allow root login with key only
sed -i 's/^#PermitRootLogin.*/PermitRootLogin prohibit-password/g' /etc/ssh/sshd_config
sed -i 's/^PermitRootLogin.*/PermitRootLogin prohibit-password/g' /etc/ssh/sshd_config
sed -i 's/^PasswordAuthentication yes/PasswordAuthentication no/g' /etc/ssh/sshd_config
systemctl restart sshd

# ==========================================
# 2. Network Check (Prevent download failures)
# ==========================================
echo "Checking network connectivity..."
# shellcheck disable=SC2034
for i in {1..20}; do
    if ping -c 1 google.com > /dev/null 2>&1; then
        echo "Network is up."
        break
    fi
    sleep 3
done

# ==========================================
# 3. System Environment & Basic Tools
# ==========================================
timedatectl set-timezone Asia/Tokyo
export DEBIAN_FRONTEND=noninteractive

# Wait for apt locks to be released
echo "Waiting for apt locks..."
while fuser /var/lib/dpkg/lock >/dev/null 2>&1 ; do sleep 1 ; done
while fuser /var/lib/apt/lists/lock >/dev/null 2>&1 ; do sleep 1 ; done

echo "Updating package lists and installing basics..."
apt update
apt upgrade -y
apt install -y sudo jq vim git zsh unzip curl wget htop tmux nethogs dnsutils locales-all

# ==========================================
# 4. Configure Oh My Zsh for Root
# ==========================================
echo "Installing Oh My Zsh..."
export ZSH="/root/.oh-my-zsh"
sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)" "" --unattended
chsh -s "$(which zsh)" root
# 5. Configure Vim (Amix) for Root
# ==========================================
echo "Installing Amix Vim config..."
rm -rf /root/.vim_runtime
git clone --depth=1 https://github.com/amix/vimrc.git /root/.vim_runtime

# FIX: Export HOME explicitly for Amix script
export HOME=/root
sh /root/.vim_runtime/install_awesome_vimrc.sh

# ==========================================
# 6. Install V2Ray - Syntax Fix
# ==========================================
echo "Installing V2Ray..."
# FIX: Avoid process substitution <(curl) syntax error in cloud-init
cd /root || exit 1
curl -L -o install-release.sh https://raw.githubusercontent.com/v2fly/fhs-install-v2ray/master/install-release.sh

# Execute with bash explicitly
chmod +x install-release.sh
/bin/bash ./install-release.sh

# Cleanup and Enable
rm install-release.sh
systemctl enable v2ray

# ==========================================
# 7. Network Optimization (BBR)
# ==========================================
echo "Enabling TCP BBR..."
echo "net.core.default_qdisc=fq" >> /etc/sysctl.conf
echo "net.ipv4.tcp_congestion_control=bbr" >> /etc/sysctl.conf
sysctl -p

# ==========================================
# 8. Finalize
# ==========================================
echo "Installation complete. Rebooting..."
touch /root/install_complete
reboot
