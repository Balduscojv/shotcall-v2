import type { Metadata } from "next";
import Link from "next/link";
import { LEGAL } from "@/lib/legal";

export const metadata: Metadata = { title: "Termos de uso" };

export default function TermsPage() {
  return (
    <>
      <h1>TERMOS DE USO</h1>
      <p className="text-sm">Atualizados em {LEGAL.updatedAt}.</p>
      <p>Ao criar uma conta ou usar o ShotCall, você concorda com estes termos. Leia também a nossa <Link href="/privacidade">política de privacidade</Link>.</p>

      <h2>O que é o ShotCall</h2>
      <p>
        Um jogo de fantasy e palpites sobre a NBA, por diversão. Você faz palpites sobre os jogos, monta um time por rodada e disputa
        rankings e ligas com outros jogadores.
      </p>

      <h2>Não é aposta</h2>
      <ul>
        <li>O ShotCall é gratuito e não envolve dinheiro real em nenhuma etapa.</li>
        <li>As fichas (F$) e os prêmios e cosméticos não têm valor em dinheiro, não podem ser comprados, vendidos, trocados ou sacados.</li>
        <li>As ligas não podem ser usadas para apostas ou prêmios em dinheiro entre os participantes.</li>
      </ul>

      <h2>Sua conta</h2>
      <ul>
        <li>Uma conta por pessoa. Você é responsável por manter sua senha segura.</li>
        <li>As informações da conta devem ser verdadeiras, e o @ não pode se passar por outra pessoa.</li>
        <li>Você pode excluir sua conta a qualquer momento em Configurações.</li>
      </ul>

      <h2>Regras de convivência</h2>
      <p>Não é permitido usar nome, @, foto, banner ou nome de liga com conteúdo ofensivo, discriminatório, sexual, violento ou ilegal, nem:</p>
      <ul>
        <li>criar contas falsas ou automatizadas para ganhar prêmios ou subir nos rankings;</li>
        <li>explorar falhas do sistema em vez de nos avisar;</li>
        <li>tentar acessar contas ou dados de outras pessoas.</li>
      </ul>
      <p>Podemos remover conteúdo, retirar prêmios obtidos de forma irregular e suspender ou excluir contas que descumpram estas regras.</p>

      <h2>Dados dos jogos e pontuação</h2>
      <p>
        Os placares e estatísticas vêm de fontes públicas de terceiros e podem atrasar, ter erros ou ficar indisponíveis. As pontuações seguem
        as estatísticas disponíveis até a apuração da rodada; correções publicadas depois disso não mudam uma rodada já apurada.
      </p>

      <h2>Marcas</h2>
      <p>
        O ShotCall não é afiliado, patrocinado nem endossado pela NBA, pelos times ou pelos jogadores. Nomes de times e jogadores são usados
        apenas para identificar os jogos e as estatísticas.
      </p>

      <h2>O serviço</h2>
      <p>
        Oferecemos o ShotCall como ele está, e podemos mudar regras de pontuação, preços, prêmios e funcionalidades para manter o jogo
        equilibrado. O serviço pode ficar fora do ar para manutenção ou por falhas.
      </p>

      <h2>Mudanças nestes termos</h2>
      <p>Se mudarmos algo importante, avisamos no app antes de a mudança valer. Continuar usando o ShotCall depois disso significa aceitar os novos termos.</p>

      <h2>Lei aplicável e contato</h2>
      <p>
        Estes termos seguem a lei brasileira. Dúvidas: <a href={`mailto:${LEGAL.contactEmail}`}>{LEGAL.contactEmail}</a>.
      </p>
    </>
  );
}
