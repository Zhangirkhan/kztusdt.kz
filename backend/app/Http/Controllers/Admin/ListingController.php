<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExchangeListingRequest;
use App\Http\Requests\Admin\UpdateExchangeListingRequest;
use App\Models\ExchangeListing;
use App\Services\ExchangeListingService;
use App\Services\RateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class ListingController extends Controller
{
    public function __construct(
        private readonly ExchangeListingService $listingService,
        private readonly RateService $rateService,
    ) {}

    public function index(): Response
    {
        $listings = ExchangeListing::query()
            ->latest('id')
            ->get()
            ->map(fn (ExchangeListing $listing): array => $this->listingService->adminPayload($listing));

        return Inertia::render('Admin/Listings/Index', [
            'listings' => $listings,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Listings/Form', $this->formSharedProps());
    }

    public function store(StoreExchangeListingRequest $request): RedirectResponse
    {
        try {
            $this->listingService->create(
                $request->user(),
                $request->validated(),
                (bool) $request->boolean('publish'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.listings.index')
            ->with('success', 'Объявление создано.');
    }

    public function edit(ExchangeListing $listing): Response
    {
        return Inertia::render('Admin/Listings/Form', [
            ...$this->formSharedProps(),
            'listing' => $this->listingService->adminPayload($listing),
        ]);
    }

    public function update(UpdateExchangeListingRequest $request, ExchangeListing $listing): RedirectResponse
    {
        try {
            $this->listingService->update(
                $listing,
                $request->validated(),
                $request->has('publish') ? $request->boolean('publish') : null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('admin.listings.index')
            ->with('success', 'Объявление обновлено.');
    }

    public function toggle(Request $request, ExchangeListing $listing): RedirectResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);

        $this->listingService->toggleActive($listing, $request->boolean('active'));

        return redirect()
            ->route('admin.listings.index')
            ->with('success', $request->boolean('active') ? 'Объявление в рынке.' : 'Объявление снято с рынка.');
    }

    public function destroy(ExchangeListing $listing): RedirectResponse
    {
        try {
            $this->listingService->delete($listing);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.listings.index')
                ->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.listings.index')
            ->with('success', 'Объявление удалено.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formSharedProps(): array
    {
        $rate = $this->rateService->cached();

        return [
            'listing' => null,
            'banks' => $this->listingService->bankOptions(),
            'paymentTerms' => $this->listingService->paymentTermOptions(),
            'marketRate' => (float) $rate['rate'],
            'marketBuyRate' => (float) $rate['buy'],
            'marketSellRate' => (float) $rate['sell'],
            'rateRange' => $this->listingService->allowedFixedRateRange(),
            'quickPhrases' => [
                'Оплата только с личного счёта',
                'После перевода нажмите «Я оплатил»',
                'Переводите точную сумму без округления',
            ],
        ];
    }
}
