# Solana PHP SDK

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.2-blue)](https://www.php.net/releases/8.2/)
![PHPUnit Tests](https://github.com/jopanel/solana-php-sdk/actions/workflows/phpunit.yml/badge.svg)


A powerful and easy-to-use PHP SDK for interacting with the Solana blockchain. This library allows developers to interact with Solana's JSON RPC and WebSocket APIs using PHP, enabling seamless integration for blockchain applications.

## 🚀 Features
- Lightweight and fully PSR-4 compliant
- [Supports **JSON RPC** methods](https://solana.com/docs/rpc)
- Built-in extensibility for future methods
- Clean and intuitive API design
- Open source and community-friendly
- PHP 8.2+ Required

## 📚 Installation

Install the library via Composer:

```bash
composer require josephopanel/solana-php-sdk
```

## 📖 Documentation

For detailed information about all available methods, visit the [API Documentation](docs/API.md).

---

### 🚀 API Methods

#### Account Methods
- [`getAccountInfo`](docs/API.md#getaccountinfo)
- [`getBalance`](docs/API.md#getbalance)
- [`getTokenAccountBalance`](docs/API.md#gettokenaccountbalance)
- [`getTokenAccountsByDelegate`](docs/API.md#gettokenaccountsbydelegate)
- [`getTokenAccountsByOwner`](docs/API.md#gettokenaccountsbyowner)
- [`getTokenLargestAccounts`](docs/API.md#gettokenlargestaccounts)
- [`getTokenSupply`](docs/API.md#gettokensupply)
- [`getLargestAccounts`](docs/API.md#getlargestaccounts)
- [`getMultipleAccounts`](docs/API.md#getmultipleaccounts)
- [`getProgramAccounts`](docs/API.md#getprogramaccounts)

#### Block Methods
- [`getBlock`](docs/API.md#getblock)
- [`getBlockCommitment`](docs/API.md#getblockcommitment)
- [`getBlockHeight`](docs/API.md#getblockheight)
- [`getBlockProduction`](docs/API.md#getblockproduction)
- [`getBlockTime`](docs/API.md#getblocktime)
- [`getBlocks`](docs/API.md#getblocks)
- [`getBlocksWithLimit`](docs/API.md#getblockswithlimit)
- [`getFirstAvailableBlock`](docs/API.md#getfirstavailableblock)
- [`isBlockhashValid`](docs/API.md#isblockhashvalid)
- [`minimumLedgerSlot`](docs/API.md#minimumledgerslot)

#### Cluster Methods
- [`getClusterNodes`](docs/API.md#getclusternodes)
- [`getEpochInfo`](docs/API.md#getepochinfo)
- [`getEpochSchedule`](docs/API.md#getepochschedule)
- [`getLeaderSchedule`](docs/API.md#getleaderschedule)
- [`getSlot`](docs/API.md#getslot)
- [`getSlotLeader`](docs/API.md#getslotleader)
- [`getSlotLeaders`](docs/API.md#getslotleaders)
- [`getSupply`](docs/API.md#getsupply)

#### Governance and Identity
- [`getIdentity`](docs/API.md#getidentity)
- [`getInflationGovernor`](docs/API.md#getinflationgovernor)
- [`getInflationRate`](docs/API.md#getinflationrate)
- [`getInflationReward`](docs/API.md#getinflationreward)
- [`getStakeMinimumDelegation`](docs/API.md#getstakeminimumdelegation)
- [`getVoteAccounts`](docs/API.md#getvoteaccounts)

#### System and Performance
- [`getFeeForMessage`](docs/API.md#getfeeformessage)
- [`getHealth`](docs/API.md#gethealth)
- [`getHighestSnapshotSlot`](docs/API.md#gethighestsnapshotslot)
- [`getRecentPerformanceSamples`](docs/API.md#getrecentperformancesamples)
- [`getRecentPrioritizationFees`](docs/API.md#getrecentprioritizationfees)

#### Transactions
- [`getLatestBlockhash`](docs/API.md#getlatestblockhash)
- [`getSignatureStatuses`](docs/API.md#getsignaturestatuses)
- [`getSignaturesForAddress`](docs/API.md#getsignaturesforaddress)
- [`getTransaction`](docs/API.md#gettransaction)
- [`getTransactionCount`](docs/API.md#gettransactioncount)
- [`simulateTransaction`](docs/API.md#simulatetransaction)
- [`sendTransaction`](docs/API.md#sendtransaction)

#### Utilities
- [`requestAirdrop`](docs/API.md#requestairdrop)


### Web Socket Methods

- [`accountSubscribe`](docs/API.md#accountsubscribe)
- [`accountUnsubscribe`](docs/API.md#accountunsubscribe)
- [`blockSubscribe`](docs/API.md#blocksubscribe)
- [`blockUnsubscribe`](docs/API.md#blockunsubscribe)
- [`logsSubscribe`](docs/API.md#logssubscribe)
- [`logsUnsubscribe`](docs/API.md#logsunsubscribe)
- [`programSubscribe`](docs/API.md#programsubscribe)
- [`programUnsubscribe`](docs/API.md#programunsubscribe)
- [`rootSubscribe`](docs/API.md#rootsubscribe)
- [`rootUnsubscribe`](docs/API.md#rootunsubscribe)
- [`signatureSubscribe`](docs/API.md#signaturesubscribe)
- [`signatureUnsubscribe`](docs/API.md#signatureunsubscribe)
- [`slotSubscribe`](docs/API.md#slotsubscribe)
- [`slotUnsubscribe`](docs/API.md#slotunsubscribe)
- [`slotsUpdatesSubscribe`](docs/API.md#slotsupdatessubscribe)
- [`slotsUpdatesUnsubscribe`](docs/API.md#slotsupdatesunsubscribe)
- [`voteSubscribe`](docs/API.md#votesubscribe)
- [`voteUnsubscribe`](docs/API.md#voteunsubscribe)

## 💻 Contributing

Contributions are welcome! If you'd like to improve the SDK or add new features, feel free to fork the repository and submit a pull request.

1. Fork the repository
2. Create a new feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m "Add new feature"`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a pull request

---

## 💰 Donations

This project is open source and developed with passion. If you’d like to support ongoing development, consider donating:

- **Solana Wallet Address**: `4izNYzN7uQac8jBDcD7NmuCpS8PqvYiHVSLXF5bY9Zrg`

Every little bit helps keep this project maintained and up-to-date. Thank you for your support! ❤️

---

## 📜 License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).

---

## 📞 Contact

For any questions, feedback, or collaboration inquiries, feel free to reach out:

- **Author**: Joseph Opanel
- **Email**: [opanelj@gmail.com](mailto:opanelj@gmail.com)
