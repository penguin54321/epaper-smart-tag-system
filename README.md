# 無紙雲控 —— 智慧電子紙標籤管理系統 (Cloud-Controlled E-Paper ESL System)

> **東吳大學資訊管理學系 畢業專題競賽 —— 第一名 (1st Place)**

本專案是一套結合 **物聯網 (IoT)**、**MQTT 即時通訊協定** 與 **Web 雲端管理平台** 的智慧電子貨架標籤 (Electronic Shelf Label, ESL) 解決方案。針對自動販賣機與中小型零售通路，解決傳統紙本標籤手動更換耗時、容易錯價且無法即時聯網調價的痛點，落實低功耗與 ESG 永續管理。

---

## 系統三大核心特色

1. **三層式端到端架構 (End-to-End IoT Architecture)**：
   - **介面層 (Web Management)**：提供後台操作介面與 RESTful API，支援商品管理、即時改價與批次排程更新。
   - **資料層 (Cloud Database)**：以關聯式資料庫集中控管商品與設備狀態，維護數據一致性與安全性。
   - **裝置層 (Hardware Edge)**：ESP32 控制核心結合低功耗 Wi-Fi，驅動 Waveshare 2.13 吋電子紙模組。

2. **MQTT 低延遲發布/訂閱廣播 (Publish/Subscribe Protocol)**：
   - 捨棄傳統輪詢 (Polling)，採用 MQTT Broker 實現伺服器至邊緣硬體的「一對多即時廣播」。
   - 當後端價格異動時，毫秒級推送更新指令，確保所有終端標籤同步，顯著降低網路傳輸頻寬與延遲。

3. **極低功耗與離線容錯機制**：
   - 利用電子紙「雙穩態（斷電仍可保持畫面）」特性與 ESP32 深度睡眠模式 (Deep Sleep)，實現極長待機時間。
   - **斷線保真**：若遇網路中斷，終端硬體保留最後一次更新價格，避免營運中斷與資訊錯亂。

---

## 開發技術與工具

- **後端與平台**：PHP, Apache, MariaDB, RESTful API
- **通訊協定**：MQTT Broker (Publish/Subscribe 模式)
- **邊緣硬體與韌體**：ESP32 開發板, Waveshare 2.13 吋電子紙模組, Arduino C/C++
- **系統分析與設計**：UML 建模、ER Model、資料庫正規化設計

---

## 系統架構與成果展示

### 1. 系統架構流程圖
![系統架構圖](docs/system_architecture.png)

### 2. 雲端後台管理儀表板 (即時連線與調價監控)
![後台管理畫面](docs/admin_dashboard.png)

### 3. 電子紙硬體端展示
![電子紙模組](docs/hardware_module.png)

---

## 完整技術文件
詳細專題研究報告請參閱：[`docs/project_report.pdf`](docs/project_report.pdf)
