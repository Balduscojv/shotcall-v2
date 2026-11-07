import type { Metadata } from "next";
import { LEGAL } from "@/lib/legal";

export const metadata: Metadata = { title: "Política de privacidade" };

export default function PrivacyPage() {
  const mail = <a href={`mailto:${LEGAL.contactEmail}`}>{LEGAL.contactEmail}</a>;

  return (
    <>
      <h1>POLÍTICA DE PRIVACIDADE</h1>
      <p className="text-sm">Atualizada em {LEGAL.updatedAt}.</p>
      <p>
        Esta política explica quais dados pessoais o ShotCall trata, para quê, e como você controla esses dados, conforme a Lei Geral de
        Proteção de Dados (Lei 13.709/2018, LGPD).
      </p>

      <h2>Que dados coletamos</h2>
      <ul>
        <li>
          <strong>Conta:</strong> nome, @, e-mail e senha (guardada só em forma criptografada, que nem nós conseguimos ler).
        </li>
        <li>
          <strong>Entrar com Google:</strong> se você usar essa opção, recebemos do Google seu nome, e-mail, foto e um identificador da conta
          Google. Não recebemos sua senha do Google.
        </li>
        <li>
          <strong>Perfil:</strong> foto e banner que você enviar.
        </li>
        <li>
          <strong>Jogo:</strong> seus palpites, times do fantasy, ligas, amizades, prêmios e notificações.
        </li>
        <li>
          <strong>Dados técnicos:</strong> endereço IP e navegador, registrados para manter sua sessão e proteger o serviço contra abuso.
        </li>
      </ul>

      <h2>Para que usamos</h2>
      <ul>
        <li>Fazer o jogo funcionar: pontuar palpites e times, montar rankings e ligas e mostrar seu perfil para outros jogadores.</li>
        <li>Mandar e-mails da conta: confirmação de e-mail e criação de senha nova.</li>
        <li>Manter o serviço seguro: limitar tentativas de login e investigar abusos.</li>
      </ul>
      <p>
        A base legal é a execução do serviço que você pediu ao criar a conta (art. 7º, V, da LGPD) e, para segurança, o legítimo interesse
        (art. 7º, IX). Não vendemos seus dados e não usamos seus dados para publicidade.
      </p>

      <h2>O que outros jogadores veem</h2>
      <p>
        Seu nome, @, foto, banner, prêmios, posição nos rankings e as ligas públicas de que você participa aparecem para outros jogadores.
        Seu e-mail nunca aparece.
      </p>

      <h2>Com quem compartilhamos</h2>
      <p>
        Só com empresas que nos ajudam a operar o serviço, como hospedagem e envio de e-mail, e apenas o necessário para isso. Se você entrar
        com o Google, o Google sabe que você usou o login dele no ShotCall.
      </p>

      <h2>Cookies</h2>
      <p>
        Usamos apenas cookies essenciais: o da sessão, o de proteção contra falsificação de requisições e, se você marcar &quot;Manter
        conectado&quot;, o que lembra o seu login. Não usamos cookies de publicidade nem de rastreamento.
      </p>

      <h2>Por quanto tempo guardamos</h2>
      <p>
        Enquanto sua conta existir. Quando você exclui a conta, apagamos seus dados pessoais e tudo o que é seu no jogo. Registros técnicos
        de acesso podem ser guardados por até 6 meses, como exige o Marco Civil da Internet (Lei 12.965/2014).
      </p>

      <h2>Seus direitos</h2>
      <ul>
        <li>Ver e corrigir seus dados: em Configurações, no seu perfil.</li>
        <li>Excluir sua conta e seus dados: em Configurações, a qualquer momento.</li>
        <li>Pedir uma cópia dos seus dados ou tirar dúvidas: escreva para {mail}.</li>
      </ul>

      <h2>Menores de idade</h2>
      <p>O ShotCall não é destinado a menores de 13 anos. Menores de 18 anos devem usar o serviço com a autorização dos responsáveis.</p>

      <h2>Mudanças nesta política</h2>
      <p>Se mudarmos algo importante, avisamos no app antes de a mudança valer.</p>

      <h2>Contato</h2>
      <p>Dúvidas e pedidos sobre seus dados: {mail}.</p>
    </>
  );
}
