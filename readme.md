# Plugin WooCommerce - Nota Fiscal para Asaas

O **Asaas Nota Fiscal** é um plugin que integra o WooCommerce com o Asaas, permitindo a emissão automática ou manual de Notas Fiscais para pedidos realizados no WooCommerce. 
Com este plugin, você pode configurar as alíquotas fiscais, reter ISS, e automatizar o processo de emissão de faturas diretamente no painel de pedidos do WooCommerce.

### Principais Funcionalidades:
- **Emissão Automática de Nota Fiscal:** Configure para que a nota fiscal seja emitida automaticamente quando o pedido atingir um determinado status.
- **Configurações Fiscais:** Defina alíquotas de ISS, COFINS, CSLL, INSS, IR e PIS.
- **Retenção de ISS:** Habilite a retenção de ISS conforme suas necessidades.
- **Integração com API do Asaas:** Envie dados dos pedidos para o Asaas para gerar notas fiscais atreladas a um pedido.
- **Emissão Individual:** Clique no botão "Emitir Nota Fiscal" na página de pedidos do WooCommerce para emitir manualmente uma nota fiscal.

**Asaas Nota Fiscal** é um plugin **não oficial**, criado para ajudar a comunidade WooCommerce na integração com o serviço de emissão de Notas Fiscais da [Asaas](https://asaas.com).

⚠ **Aviso importante:** A configuração de alíquotas e impostos deve ser feita com a supervisão de um contador, garantindo que todos os dados estejam em conformidade com a legislação vigente.

⚠ **Observação:** O Asaas aceita apenas a emissão de **Nota Fiscal Eletrônica de Serviços (NFS-e).**

## Instalação

1. **Faça o Download do Plugin:**
   - Baixe o arquivo ZIP do plugin.

2. **Envie para o seu Site WordPress:**
   - No painel administrativo do WordPress, vá para **Plugins > Adicionar Novo**.
   - Clique em **Enviar Plugin** e selecione o arquivo ZIP do plugin.
   - Clique em **Instalar Agora**.

3. **Ative o Plugin:**
   - Após a instalação, clique em **Ativar Plugin**.

4. **Configure as Opções:**
   - Vá para **WooCommerce > Asaas Nota Fiscal**.
   - Configure as opções de emissão automática, alíquotas fiscais, e insira a sua **Chave de API** do Asaas.
   - Salve as configurações.

## Uso

### 1. Emissão Automática de Nota Fiscal:
   - Configure no painel de configurações do plugin para ativar a emissão automática.
   - Defina o status do pedido que disparará a emissão da nota fiscal (por exemplo, "Processando" ou "Concluído").
   - Preencha as alíquotas fiscais conforme sua necessidade.

### 2. Emitir Nota Fiscal Manualmente:
   - Vá para **WooCommerce > Pedidos**.
   - Abra um pedido específico.
   - No painel de ações do pedido, você verá a opção **"Emitir Nota Fiscal"**.
   - Selecione essa ação e clique em **"Aplicar"**.
   - Sua nota fiscal será emitida no mesmo dia.

## FAQ

### 1. Onde encontrar a chave de API do Asaas?

   - Acesse sua conta no Asaas.
   - Vá para **Configurações > Integrações**.
   - Copie a chave de API e insira nas configurações do plugin.

### 2. Como definir as alíquotas fiscais?

   - Nas configurações do plugin, preencha os campos correspondentes às alíquotas de ISS, COFINS, CSLL, INSS, IR e PIS conforme as regulamentações vigentes.

### 3. Posso desativar a emissão automática?

   - Sim, nas configurações do plugin você pode desativar a emissão automática. A nota fiscal pode ser emitida manualmente a partir da página de pedidos.

### 4. Preciso do plugin da Asaas para emitir as Notas Fiscais?

   - Não, nosso plugin funciona independnete do plugin da Asaas, podendo escolher em usar qualquer outro gateway de pagamento.

## Screenshots

1. **Tela de Configurações:**
   - Configure as opções de emissão automática, alíquotas fiscais e chave de API.
![Captura de tela 2024-10-20 004758](https://github.com/user-attachments/assets/bbe3e207-90da-4d8d-8b59-9608917aa5d7)

2. **Ação "Emitir Nota Fiscal" no Pedido:**
   - Botão disponível na página de pedidos para emissão manual da nota fiscal.
![Captura de tela 2024-10-20 004852](https://github.com/user-attachments/assets/bf6d0d90-d431-4524-893a-fd88b7856ea6)


## API Asaas

Este plugin utiliza serviços de terceiros fornecidos pela Asaas para processar e emitir Notas Fiscais. 
Isso envolve o envio de dados como endereço do cliente, valor da compra, CPF/CNPJ, entre outros, para a Asaas através da sua API.

- 🔗 [Site da Asaas](https://asaas.com)  
- 📄 [Documentação da API Asaas](https://docs.asaas.com/)  
- 📜 [Termos de Uso da Asaas](https://ajuda.asaas.com/pt-BR/articles/102021-termos-e-condicoes-de-uso)  
- 🔒 [Política de Privacidade da Asaas](https://ajuda.asaas.com/pt-BR/articles/102029-politica-de-privacidade)

**Nota:** Ao utilizar este plugin, você concorda com os termos de uso e a política de privacidade da Asaas. Assegure-se de que o uso destes serviços está em conformidade com as legislações aplicáveis de proteção de dados.
